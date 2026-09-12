<?php

namespace App\Services\Recommendations;

use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Models\ReadingList;
use App\Models\Review;
use App\Models\User;
use App\Services\Literature\CanonicalLiteratureSearch;
use App\Services\Literature\CanonicalWorkIdentity;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PersonalRecommendationService
{
    public function __construct(
        private readonly CanonicalLiteratureSearch $canonicalSearch,
        private readonly CanonicalWorkIdentity $identity,
    ) {}

    /**
     * @return Collection<int, array{literature: Literature, score: float, reason: string, cold_start: bool}>
     */
    public function recommend(User $user, int $limit = 12): Collection
    {
        $user->loadMissing([
            'readingLists.literature.authors',
            'readingLists.literature.categories',
            'readingLists.literature.sourceMapping',
            'reviews.literature.authors',
            'reviews.literature.categories',
            'reviews.literature.sourceMapping',
            'favoriteLiteratures.authors',
            'favoriteLiteratures.categories',
            'favoriteLiteratures.sourceMapping',
            'favoriteAuthors',
        ]);

        $candidates = $this->canonicalSearch->query()
            ->latest('updated_at')
            ->latest('id')
            ->limit(300)
            ->get();

        if ($candidates->isEmpty()) {
            return collect();
        }

        $excludedKeys = $user->readingLists
            ->map(fn (ReadingList $entry): string => $this->identity->key($entry->literature))
            ->merge($user->favoriteLiteratures->map(fn (Literature $literature): string => $this->identity->key($literature)))
            ->unique()
            ->flip();
        $taste = $this->tasteProfile($user);
        $coldStart = $taste['meaningful_keys']->count() < 2;
        [$sourceIdsByKey, $keyBySourceId] = $this->sourceIdentities($candidates);
        $popularity = $this->popularityByKey($keyBySourceId);
        $similarity = $coldStart
            ? collect()
            : $this->similarReaderPopularity($user, $taste['meaningful_keys'], $sourceIdsByKey, $keyBySourceId);

        return $candidates
            ->reject(fn (Literature $literature): bool => $excludedKeys->has($this->identity->key($literature)))
            ->map(function (Literature $literature) use ($taste, $popularity, $similarity, $coldStart): array {
                $key = $this->identity->key($literature);
                $genreScores = $literature->categories->mapWithKeys(
                    fn ($category): array => [$category->name => (float) ($taste['genres'][$category->name] ?? 0)],
                );
                $authorScores = $literature->authors->mapWithKeys(
                    fn ($author): array => [$author->id => (float) ($taste['authors'][$author->id] ?? 0)],
                );
                $topGenre = $genreScores->sortDesc()->keys()->first();
                $topAuthorId = $authorScores->sortDesc()->keys()->first();
                $genreScore = (float) ($genreScores->max() ?? 0);
                $authorScore = (float) ($authorScores->max() ?? 0);
                $typeScore = (float) ($taste['types'][$literature->type] ?? 0);
                $localPopularity = (float) ($popularity[$key] ?? 0);
                $similarReaderScore = (float) ($similarity[$key] ?? 0);
                $score = $coldStart
                    ? $localPopularity
                    : ($genreScore * 2.4)
                        + ($authorScore * 3.2)
                        + ($typeScore * 0.8)
                        + $localPopularity
                        + ($similarReaderScore * 2.2);

                return [
                    'literature' => $literature,
                    'score' => round($score, 4),
                    'reason' => $this->reason(
                        $literature,
                        is_string($topGenre) && $genreScore > 0 ? $topGenre : null,
                        is_numeric($topAuthorId) && $authorScore > 0 ? (int) $topAuthorId : null,
                        $taste['author_sources'],
                        $similarReaderScore,
                        $coldStart,
                    ),
                    'cold_start' => $coldStart,
                ];
            })
            ->sortByDesc(fn (array $item): string => sprintf('%012.4f-%010d', $item['score'], $item['literature']->id))
            ->take(max(1, min($limit, 12)))
            ->values();
    }

    /**
     * @return array{
     *     genres: array<string, float>,
     *     authors: array<int, float>,
     *     author_sources: array<int, string>,
     *     types: array<string, float>,
     *     meaningful_keys: Collection<int, string>
     * }
     */
    private function tasteProfile(User $user): array
    {
        $genres = [];
        $authors = [];
        $authorSources = [];
        $types = [];
        $meaningfulKeys = collect();
        $ratedKeys = collect();

        foreach ($user->reviews->sortByDesc('updated_at')->unique(fn (Review $review): string => $this->identity->key($review->literature)) as $review) {
            if ($review->hidden_at !== null || $review->rating < 3.5) {
                continue;
            }

            $weight = max(1.0, ((float) $review->rating - 2.5) * 2.0);
            $key = $this->identity->key($review->literature);
            $this->addTaste($genres, $authors, $types, $review->literature, $weight);

            foreach ($review->literature->authors as $author) {
                $authorSources[$author->id] ??= $review->literature->displayTitle();
            }

            $meaningfulKeys->push($key);
            $ratedKeys->push($key);
        }

        foreach ($user->readingLists->where('status', 'completed') as $entry) {
            $key = $this->identity->key($entry->literature);

            if (! $ratedKeys->contains($key)) {
                $this->addTaste($genres, $authors, $types, $entry->literature, 1.5);
            }

            $meaningfulKeys->push($key);
        }

        foreach ($user->favoriteLiteratures as $literature) {
            $this->addTaste($genres, $authors, $types, $literature, 5.0);
            $meaningfulKeys->push($this->identity->key($literature));
        }

        foreach ($user->readingLists->where('status', 'want_to_read') as $entry) {
            $this->addTaste($genres, $authors, $types, $entry->literature, 0.5);
        }

        foreach ($user->favoriteAuthors as $author) {
            $authors[$author->id] = ($authors[$author->id] ?? 0.0) + 7.0;
        }

        return [
            'genres' => $genres,
            'authors' => $authors,
            'author_sources' => $authorSources,
            'types' => $types,
            'meaningful_keys' => $meaningfulKeys->unique()->values(),
        ];
    }

    /**
     * @param  array<string, float>  $genres
     * @param  array<int, float>  $authors
     * @param  array<string, float>  $types
     */
    private function addTaste(
        array &$genres,
        array &$authors,
        array &$types,
        Literature $literature,
        float $weight,
    ): void {
        foreach ($literature->categories as $category) {
            $genres[$category->name] = ($genres[$category->name] ?? 0.0) + $weight;
        }

        foreach ($literature->authors as $author) {
            $authors[$author->id] = ($authors[$author->id] ?? 0.0) + $weight;
        }

        $types[$literature->type] = ($types[$literature->type] ?? 0.0) + $weight;
    }

    /**
     * @param  EloquentCollection<int, Literature>  $candidates
     * @return array{Collection<string, Collection<int, int>>, Collection<int, string>}
     */
    private function sourceIdentities(EloquentCollection $candidates): array
    {
        $canonicalIds = $candidates->pluck('sourceMapping.canonical_work_id')->filter()->unique()->values();
        $sourceIdsByKey = LiteratureSourceMapping::query()
            ->whereIn('canonical_work_id', $canonicalIds)
            ->get(['canonical_work_id', 'literature_id'])
            ->groupBy(fn (LiteratureSourceMapping $mapping): string => 'canonical:'.$mapping->canonical_work_id)
            ->map(fn (Collection $mappings): Collection => $mappings->pluck('literature_id'));

        foreach ($candidates->filter(fn (Literature $literature): bool => $literature->sourceMapping === null) as $legacy) {
            $sourceIdsByKey->put('literature:'.$legacy->id, collect([$legacy->id]));
        }

        $keyBySourceId = collect();

        foreach ($sourceIdsByKey as $key => $sourceIds) {
            foreach ($sourceIds as $sourceId) {
                $keyBySourceId->put((int) $sourceId, $key);
            }
        }

        return [$sourceIdsByKey, $keyBySourceId];
    }

    /** @param Collection<int, string> $keyBySourceId */
    private function popularityByKey(Collection $keyBySourceId): Collection
    {
        $sourceIds = $keyBySourceId->keys();
        $readingCounts = ReadingList::query()
            ->whereIn('literature_id', $sourceIds)
            ->whereIn('status', ['reading', 'completed'])
            ->selectRaw('literature_id, COUNT(*) as total')
            ->groupBy('literature_id')
            ->pluck('total', 'literature_id');
        $reviewCounts = Review::query()
            ->whereIn('literature_id', $sourceIds)
            ->whereNull('hidden_at')
            ->where('rating', '>=', 4)
            ->selectRaw('literature_id, COUNT(*) as total')
            ->groupBy('literature_id')
            ->pluck('total', 'literature_id');
        $favoriteCounts = DB::table('user_favorite_literatures')
            ->whereIn('literature_id', $sourceIds)
            ->selectRaw('literature_id, COUNT(*) as total')
            ->groupBy('literature_id')
            ->pluck('total', 'literature_id');
        $scores = collect();

        foreach ($keyBySourceId as $sourceId => $key) {
            $raw = ((int) ($readingCounts[$sourceId] ?? 0) * 2)
                + ((int) ($reviewCounts[$sourceId] ?? 0) * 2)
                + ((int) ($favoriteCounts[$sourceId] ?? 0) * 3);
            $scores->put($key, (float) ($scores[$key] ?? 0) + $raw);
        }

        return $scores->map(fn (float|int $score): float => log(1 + $score) * 2.5);
    }

    /**
     * @param  Collection<int, string>  $meaningfulKeys
     * @param  Collection<string, Collection<int, int>>  $sourceIdsByKey
     * @param  Collection<int, string>  $keyBySourceId
     */
    private function similarReaderPopularity(
        User $user,
        Collection $meaningfulKeys,
        Collection $sourceIdsByKey,
        Collection $keyBySourceId,
    ): Collection {
        $likedSourceIds = $meaningfulKeys
            ->flatMap(fn (string $key): Collection => $sourceIdsByKey[$key] ?? collect())
            ->unique()
            ->values();

        if ($likedSourceIds->isEmpty()) {
            return collect();
        }

        $similarReaderIds = ReadingList::query()
            ->where('user_id', '!=', $user->id)
            ->whereIn('literature_id', $likedSourceIds)
            ->where('status', 'completed')
            ->selectRaw('user_id, COUNT(DISTINCT literature_id) as overlap')
            ->groupBy('user_id')
            ->orderByDesc('overlap')
            ->limit(50)
            ->pluck('user_id');

        if ($similarReaderIds->isEmpty()) {
            return collect();
        }

        $countsBySource = ReadingList::query()
            ->whereIn('user_id', $similarReaderIds)
            ->whereIn('literature_id', $keyBySourceId->keys())
            ->where('status', 'completed')
            ->selectRaw('literature_id, COUNT(DISTINCT user_id) as total')
            ->groupBy('literature_id')
            ->pluck('total', 'literature_id');
        $scores = collect();

        foreach ($countsBySource as $literatureId => $count) {
            $key = $keyBySourceId[(int) $literatureId];
            $scores->put($key, (float) ($scores[$key] ?? 0) + (float) $count);
        }

        return $scores;
    }

    /** @param array<int, string> $authorSources */
    private function reason(
        Literature $literature,
        ?string $genre,
        ?int $authorId,
        array $authorSources,
        float $similarReaderScore,
        bool $coldStart,
    ): string {
        if ($coldStart) {
            return 'Trending with Literahaven readers';
        }

        if ($authorId !== null && isset($authorSources[$authorId])) {
            return 'Because you rated '.$authorSources[$authorId].' highly';
        }

        if ($authorId !== null) {
            $author = $literature->authors->firstWhere('id', $authorId);

            if ($author !== null) {
                return 'Because you like '.$author->name;
            }
        }

        if ($genre !== null) {
            return 'Because you liked '.$genre;
        }

        if ($similarReaderScore > 0) {
            return 'Popular among readers with similar interests';
        }

        return 'Because you often enjoy '.$literature->typeLabel();
    }
}
