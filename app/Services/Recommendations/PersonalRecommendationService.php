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
use Illuminate\Support\Str;

final class PersonalRecommendationService
{
    private const CANDIDATES_PER_SOURCE = 100;

    private const TOP_TASTE_GENRES = 5;

    private const TOP_TASTE_AUTHORS = 5;

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

        $excludedKeys = $user->readingLists
            ->map(fn (ReadingList $entry): string => $this->identity->key($entry->literature))
            ->merge($user->favoriteLiteratures->map(fn (Literature $literature): string => $this->identity->key($literature)))
            ->unique()
            ->flip();
        $taste = $this->tasteProfile($user);
        $coldStart = $taste['meaningful_keys']->count() < 2;
        $similarity = $coldStart
            ? collect()
            : $this->similarReaderPopularity($user, $taste['meaningful_keys']);
        $candidateIds = $this->genreCandidateIds($taste['genres'])
            ->merge($this->authorCandidateIds($taste['authors']))
            ->merge($this->representativeIdsForKeys($similarity->sortDesc()->keys()->take(self::CANDIDATES_PER_SOURCE)))
            ->merge($this->popularCandidateIds())
            ->unique()
            ->values();

        if ($candidateIds->isEmpty()) {
            return collect();
        }

        $candidates = $this->canonicalSearch->query()
            ->whereKey($candidateIds)
            ->get();

        if ($candidates->isEmpty()) {
            return collect();
        }

        [$sourceIdsByKey, $keyBySourceId] = $this->sourceIdentities($candidates);
        $popularity = $this->popularityByKey($keyBySourceId);

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

    /** @param array<string, float> $genreScores */
    private function genreCandidateIds(array $genreScores): Collection
    {
        $genres = collect($genreScores)
            ->sortDesc()
            ->take(self::TOP_TASTE_GENRES)
            ->keys();

        if ($genres->isEmpty()) {
            return collect();
        }

        $perGenre = max(1, (int) ceil(self::CANDIDATES_PER_SOURCE / $genres->count()));

        return $genres->flatMap(function (string $genre) use ($perGenre): Collection {
            return $this->canonicalSearch->representativeQuery()
                ->where(function ($literatures) use ($genre): void {
                    $literatures
                        ->whereHas('categories', fn ($categories) => $categories->where('name', $genre))
                        ->orWhereHas(
                            'sourceMapping.canonicalWork.literatures.categories',
                            fn ($categories) => $categories->where('name', $genre),
                        );
                })
                ->orderBy('literatures.id')
                ->limit($perGenre)
                ->pluck('literatures.id');
        })->unique()->take(self::CANDIDATES_PER_SOURCE)->values();
    }

    /** @param array<int, float> $authorScores */
    private function authorCandidateIds(array $authorScores): Collection
    {
        $authorIds = collect($authorScores)
            ->sortDesc()
            ->take(self::TOP_TASTE_AUTHORS)
            ->keys()
            ->map(fn ($authorId): int => (int) $authorId);

        if ($authorIds->isEmpty()) {
            return collect();
        }

        $perAuthor = max(1, (int) ceil(self::CANDIDATES_PER_SOURCE / $authorIds->count()));

        return $authorIds->flatMap(function (int $authorId) use ($perAuthor): Collection {
            return $this->canonicalSearch->representativeQuery()
                ->where(function ($literatures) use ($authorId): void {
                    $literatures
                        ->whereHas('authors', fn ($authors) => $authors->whereKey($authorId))
                        ->orWhereHas(
                            'sourceMapping.canonicalWork.literatures.authors',
                            fn ($authors) => $authors->whereKey($authorId),
                        );
                })
                ->orderBy('literatures.id')
                ->limit($perAuthor)
                ->pluck('literatures.id');
        })->unique()->take(self::CANDIDATES_PER_SOURCE)->values();
    }

    /** @return Collection<int, int> */
    private function popularCandidateIds(): Collection
    {
        $seedLimit = self::CANDIDATES_PER_SOURCE * 2;
        $sourceIds = ReadingList::query()
            ->whereIn('status', ['reading', 'completed'])
            ->selectRaw('literature_id, COUNT(*) as total')
            ->groupBy('literature_id')
            ->orderByDesc('total')
            ->limit($seedLimit)
            ->pluck('literature_id')
            ->merge(Review::query()
                ->whereNull('hidden_at')
                ->where('rating', '>=', 4)
                ->selectRaw('literature_id, COUNT(*) as total')
                ->groupBy('literature_id')
                ->orderByDesc('total')
                ->limit($seedLimit)
                ->pluck('literature_id'))
            ->merge(DB::table('user_favorite_literatures')
                ->selectRaw('literature_id, COUNT(*) as total')
                ->groupBy('literature_id')
                ->orderByDesc('total')
                ->limit($seedLimit)
                ->pluck('literature_id'))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $representativeIds = $this->representativeIdsForSourceIds($sourceIds);
        $representativeKeys = $this->keysForSourceIds($representativeIds)->values();
        $keyBySourceId = $this->keysForSourceIds($this->sourceIdsForKeys($representativeKeys));
        $rankedIds = $this->popularityByKey($keyBySourceId)
            ->sortDesc()
            ->keys()
            ->take(self::CANDIDATES_PER_SOURCE)
            ->pipe(fn (Collection $keys): Collection => $this->representativeIdsForKeys($keys))
            ->values();

        if ($rankedIds->count() >= self::CANDIDATES_PER_SOURCE) {
            return $rankedIds->values();
        }

        $fillIds = $this->canonicalSearch->representativeQuery()
            ->whereNotIn('literatures.id', $rankedIds)
            ->orderBy('literatures.id')
            ->limit(self::CANDIDATES_PER_SOURCE - $rankedIds->count())
            ->pluck('literatures.id');

        return $rankedIds->merge($fillIds)->unique()->values();
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

    /** @param Collection<int, string> $meaningfulKeys */
    private function similarReaderPopularity(
        User $user,
        Collection $meaningfulKeys,
    ): Collection {
        $likedSourceIds = $this->sourceIdsForKeys($meaningfulKeys);

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
            ->where('status', 'completed')
            ->selectRaw('literature_id, COUNT(DISTINCT user_id) as total')
            ->groupBy('literature_id')
            ->orderByDesc('total')
            ->limit(self::CANDIDATES_PER_SOURCE * 2)
            ->pluck('total', 'literature_id');
        $keyBySourceId = $this->keysForSourceIds($countsBySource->keys());
        $scores = collect();

        foreach ($countsBySource as $literatureId => $count) {
            $key = $keyBySourceId[(int) $literatureId] ?? null;

            if ($key === null) {
                continue;
            }

            $scores->put($key, (float) ($scores[$key] ?? 0) + (float) $count);
        }

        return $scores;
    }

    /** @param Collection<int, int|string> $sourceIds */
    private function representativeIdsForSourceIds(Collection $sourceIds): Collection
    {
        $sourceIds = $sourceIds->map(fn ($id): int => (int) $id)->unique()->values();
        $preferredBySourceId = LiteratureSourceMapping::query()
            ->join('canonical_works', 'canonical_works.id', '=', 'literature_source_mappings.canonical_work_id')
            ->whereIn('literature_source_mappings.literature_id', $sourceIds)
            ->whereNotNull('canonical_works.preferred_literature_id')
            ->pluck('canonical_works.preferred_literature_id', 'literature_source_mappings.literature_id');

        return $sourceIds
            ->map(fn (int $id): int => (int) ($preferredBySourceId[$id] ?? $id))
            ->unique()
            ->values();
    }

    /** @param Collection<int, string> $keys */
    private function representativeIdsForKeys(Collection $keys): Collection
    {
        $canonicalIds = $keys
            ->filter(fn (string $key): bool => str_starts_with($key, 'canonical:'))
            ->map(fn (string $key): int => (int) Str::after($key, 'canonical:'));
        $legacyIds = $keys
            ->filter(fn (string $key): bool => str_starts_with($key, 'literature:'))
            ->map(fn (string $key): int => (int) Str::after($key, 'literature:'));
        $preferredIds = DB::table('canonical_works')
            ->whereIn('id', $canonicalIds)
            ->whereNotNull('preferred_literature_id')
            ->pluck('preferred_literature_id');

        return $preferredIds
            ->merge($legacyIds)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    /** @param Collection<int, string> $keys */
    private function sourceIdsForKeys(Collection $keys): Collection
    {
        $canonicalIds = $keys
            ->filter(fn (string $key): bool => str_starts_with($key, 'canonical:'))
            ->map(fn (string $key): int => (int) Str::after($key, 'canonical:'));
        $legacyIds = $keys
            ->filter(fn (string $key): bool => str_starts_with($key, 'literature:'))
            ->map(fn (string $key): int => (int) Str::after($key, 'literature:'));

        return LiteratureSourceMapping::query()
            ->whereIn('canonical_work_id', $canonicalIds)
            ->pluck('literature_id')
            ->merge($legacyIds)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    /** @param Collection<int, int|string> $sourceIds */
    private function keysForSourceIds(Collection $sourceIds): Collection
    {
        $sourceIds = $sourceIds->map(fn ($id): int => (int) $id)->unique()->values();
        $canonicalBySourceId = LiteratureSourceMapping::query()
            ->whereIn('literature_id', $sourceIds)
            ->pluck('canonical_work_id', 'literature_id');

        return $sourceIds->mapWithKeys(fn (int $id): array => [
            $id => isset($canonicalBySourceId[$id])
                ? 'canonical:'.$canonicalBySourceId[$id]
                : 'literature:'.$id,
        ]);
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
