<?php

namespace App\Services\Literature;

use App\Models\Literature;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CatalogResultRanker
{
    public function __construct(private NovelCatalogClassifier $novelClassifier) {}

    /**
     * Score source records in SQL, fold their best match into the existing preferred
     * canonical record, then let the caller limit or paginate the result.
     *
     * @param  Builder<Literature>  $matches
     * @return Builder<Literature>
     */
    public function rankQuery(Builder $matches, string $query): Builder
    {
        $term = Str::lower(Str::squish($query));
        $pattern = '%'.$this->escapeLike($term).'%';
        $prefix = $this->escapeLike($term).'%';
        $matches
            ->leftJoin('literature_source_mappings as search_mapping', 'search_mapping.literature_id', '=', 'literatures.id')
            ->leftJoin('canonical_works as search_work', 'search_work.id', '=', 'search_mapping.canonical_work_id')
            ->leftJoin('literature_metadata_overrides as search_curated', 'search_curated.canonical_work_id', '=', 'search_work.id')
            ->leftJoin('literature_metadata_overrides as search_local', 'search_local.literature_id', '=', 'literatures.id')
            ->where(fn (Builder $eligible) => $eligible
                ->whereNull('search_mapping.id')
                ->orWhereNotNull('search_work.preferred_literature_id'));

        $title = $this->field('title');
        $original = $this->field('original_title');
        $displayTitle = "CASE WHEN NULLIF(TRIM(search_curated.title), '') IS NOT NULL THEN search_curated.title
            WHEN search_curated.id IS NULL AND NULLIF(TRIM(search_local.title), '') IS NOT NULL THEN search_local.title
            WHEN literatures.type IN ('manga', 'manhwa') THEN literatures.title
            ELSE COALESCE({$original}, literatures.title) END";
        $primary = "LOWER(TRIM({$displayTitle}))";
        $alternate = "LOWER(TRIM(COALESCE({$title}, '')))";
        $native = "LOWER(TRIM(COALESCE({$original}, '')))";
        $canonical = "LOWER(TRIM(COALESCE(search_work.canonical_title, '')))";

        $authorScore = DB::table('author_literature as search_creators')
            ->join('authors as search_authors', 'search_authors.id', '=', 'search_creators.author_id')
            ->leftJoin('author_aliases as search_aliases', 'search_aliases.author_id', '=', 'search_authors.id')
            ->whereColumn('search_creators.literature_id', 'literatures.id')
            ->selectRaw("MAX(CASE
                WHEN LOWER(search_authors.name) = ? OR LOWER(search_aliases.name) = ? THEN 350
                WHEN LOWER(search_authors.name) LIKE ? ESCAPE '!' OR LOWER(search_aliases.name) LIKE ? ESCAPE '!' THEN 280
                WHEN LOWER(search_authors.name) LIKE ? ESCAPE '!' OR LOWER(search_aliases.name) LIKE ? ESCAPE '!' THEN 220
                ELSE 0 END)", [$term, $term, $prefix, $prefix, $pattern, $pattern]);

        // Reuse the joined metadata instead of running another set of correlated
        // metadata/canonical EXISTS queries for every source candidate.
        $matches->where(function (Builder $search) use ($pattern, $authorScore): void {
            foreach ([
                'literatures.title', 'literatures.original_title',
                'search_local.title', 'search_local.original_title',
                'search_curated.title', 'search_curated.original_title',
                'search_work.canonical_title',
            ] as $column) {
                $search->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$pattern]);
            }

            $search->orWhereRaw("COALESCE(({$authorScore->toSql()}), 0) > 0", $authorScore->getBindings())
                ->orWhereHas('categories', fn (Builder $categories) => $categories
                    ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern]));
        });

        $relevance = "CASE
            WHEN {$primary} = ? THEN 1000
            WHEN {$alternate} = ? OR {$native} = ? OR {$canonical} = ? THEN 900
            WHEN {$primary} LIKE ? ESCAPE '!' THEN 700
            WHEN {$alternate} LIKE ? ESCAPE '!' OR {$native} LIKE ? ESCAPE '!' OR {$canonical} LIKE ? ESCAPE '!' THEN 650
            WHEN {$primary} LIKE ? ESCAPE '!' THEN 550
            WHEN {$alternate} LIKE ? ESCAPE '!' OR {$native} LIKE ? ESCAPE '!' OR {$canonical} LIKE ? ESCAPE '!' THEN 500
            ELSE COALESCE(({$authorScore->toSql()}), 0) END";
        $bindings = [
            $term, $term, $term, $term,
            $prefix, $prefix, $prefix, $prefix,
            $pattern, $pattern, $pattern, $pattern,
            ...$authorScore->getBindings(),
        ];

        $this->novelClassifier->constrainSearch($matches, $displayTitle);
        $sources = $matches
            ->selectRaw('COALESCE(search_work.preferred_literature_id, literatures.id) AS representative_id')
            ->selectRaw("({$relevance}) AS relevance", $bindings);
        $scores = DB::query()->fromSub($sources->toBase(), 'search_sources')
            ->select('representative_id')
            ->selectRaw('MAX(relevance) AS relevance')
            ->groupBy('representative_id');

        // Quality and popularity stay below the exact-title tier gap.
        // Provider refresh timestamps do not rank hits.
        return Literature::query()
            ->joinSub($scores, 'search_scores', 'search_scores.representative_id', '=', 'literatures.id')
            ->leftJoin('literature_source_mappings as search_mapping', 'search_mapping.literature_id', '=', 'literatures.id')
            ->leftJoin('literature_metadata_overrides as search_curated', 'search_curated.canonical_work_id', '=', 'search_mapping.canonical_work_id')
            ->leftJoin('literature_metadata_overrides as search_local', 'search_local.literature_id', '=', 'literatures.id')
            ->leftJoinSub($this->popularity(), 'search_popularity', 'search_popularity.representative_id', '=', 'literatures.id')
            ->whereIn('literatures.type', Literature::supportedTypes())
            ->select('literatures.*')
            ->selectRaw('(search_scores.relevance + '.$this->metadataScore().'
                + 20.0 * COALESCE(search_popularity.readers, 0) / (COALESCE(search_popularity.readers, 0) + 10.0)) AS search_score')
            ->orderByDesc('search_score')
            ->orderByDesc('literatures.id');
    }

    private function field(string $column): string
    {
        return "COALESCE(search_curated.{$column}, CASE WHEN search_curated.id IS NULL THEN search_local.{$column} END, literatures.{$column})";
    }

    private function metadataScore(): string
    {
        $parts = ['CASE WHEN EXISTS (SELECT 1 FROM author_literature WHERE author_literature.literature_id = literatures.id) THEN 6 ELSE 0 END'];

        foreach (['identifier' => 4, 'publisher' => 4, 'cover_url' => 8, 'synopsis' => 6, 'publication_year' => 4, 'knowledge_graph_id' => 4] as $field => $weight) {
            $expression = in_array($field, ['identifier', 'knowledge_graph_id'], true)
                ? 'literatures.'.$field
                : $this->field($field);

            if ($field === 'cover_url') {
                $expression = 'COALESCE(search_curated.cover_path, CASE WHEN search_curated.id IS NULL THEN search_local.cover_path END, '.$expression.')';
            }

            $condition = $field === 'publication_year'
                ? "{$expression} IS NOT NULL"
                : "NULLIF(TRIM({$expression}), '') IS NOT NULL";
            $parts[] = "CASE WHEN {$condition} THEN {$weight} ELSE 0 END";
        }

        return implode(' + ', $parts);
    }

    private function popularity(): QueryBuilder
    {
        $readers = DB::table('reading_lists')
            ->select(['literature_id', 'user_id'])
            ->whereIn('status', ['reading', 'completed'])
            ->unionAll(DB::table('reviews')->select(['literature_id', 'user_id'])->whereNull('hidden_at'))
            ->unionAll(DB::table('user_favorite_literatures')->select(['literature_id', 'user_id']));
        $representative = 'COALESCE(popularity_work.preferred_literature_id, search_readers.literature_id)';

        return DB::query()->fromSub($readers, 'search_readers')
            ->join('users as search_users', 'search_users.id', '=', 'search_readers.user_id')
            ->whereNull('search_users.deactivated_at')
            ->leftJoin('literature_source_mappings as popularity_mapping', 'popularity_mapping.literature_id', '=', 'search_readers.literature_id')
            ->leftJoin('canonical_works as popularity_work', 'popularity_work.id', '=', 'popularity_mapping.canonical_work_id')
            ->selectRaw($representative.' AS representative_id')
            ->selectRaw('COUNT(DISTINCT search_readers.user_id) AS readers')
            ->groupByRaw($representative);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    /**
     * Legacy collection API retained for existing callers. Search endpoints use
     * rankQuery so no candidate is discarded by a pre-ranking collection limit.
     *
     * @param  Collection<int, Literature>  $literatures
     * @return Collection<int, Literature>
     */
    public function rank(Collection $literatures, string $query): Collection
    {
        $query = Str::squish($query);

        if ($query === '') {
            return $literatures->values();
        }

        return $literatures
            ->filter(fn (Literature $literature): bool => $this->isEligible($literature))
            ->sort(function (Literature $left, Literature $right) use ($query): int {
                $scoreComparison = $this->score($right, $query) <=> $this->score($left, $query);

                if ($scoreComparison !== 0) {
                    return $scoreComparison;
                }

                $updatedComparison = $right->updated_at <=> $left->updated_at;

                return $updatedComparison !== 0 ? $updatedComparison : $right->id <=> $left->id;
            })
            ->unique(fn (Literature $literature): string => $this->canonicalKey($literature))
            ->values();
    }

    private function isEligible(Literature $literature): bool
    {
        if ($literature->type !== 'novel') {
            return true;
        }

        return ! $this->novelClassifier->isExplicitlyExcluded(
            title: $literature->displayTitle(),
            categories: $literature->categories->pluck('name')->all(),
        );
    }

    private function score(Literature $literature, string $query): int
    {
        $title = $this->normalize($literature->displayTitle());
        $alternateTitle = $this->normalize($literature->alternateTitle() ?? '');
        $normalizedQuery = $this->normalize($query);
        $score = 0;

        if ($title === $normalizedQuery) {
            $score += 500;
        } elseif (Str::startsWith($title, $normalizedQuery.' ')) {
            $score += 280;
        } elseif (Str::contains($title, $normalizedQuery)) {
            $score += 180;
        }

        if ($alternateTitle === $normalizedQuery) {
            $score += 420;
        } elseif ($alternateTitle !== '' && Str::contains($alternateTitle, $normalizedQuery)) {
            $score += 140;
        }

        $queryWords = Str::of($normalizedQuery)->explode(' ')->filter()->unique();
        $matchedWords = $queryWords->filter(fn (string $word): bool => Str::contains($title, $word))->count();

        if ($queryWords->isNotEmpty()) {
            $score += (int) round(($matchedWords / $queryWords->count()) * 120);
        }

        $score += $literature->authors->isNotEmpty() ? 90 : -120;
        $score += filled($literature->identifier) ? 70 : -30;
        $score += filled($literature->publisher) ? 35 : 0;
        $score += filled($literature->cover_url) ? 35 : -30;
        $score += $literature->publication_year !== null ? 20 : 0;
        $score += filled($literature->knowledge_graph_id) ? 180 : 0;

        $classificationText = Str::lower(implode(' ', [
            $literature->title,
            $literature->tagline,
            $literature->categories->pluck('name')->implode(' '),
        ]));

        if (preg_match(
            '/\b(unofficial|study guide|teacher(?:\x{2019}|\x{0027})?s? guide|workbook|summary|analysis|companion|quiz|lesson plans?|musical|coloring|colouring|activity book|journal|notebook|handbook)\b/u',
            $classificationText,
        ) === 1) {
            $score -= 320;
        }

        if (preg_match('/\b(fiction|fantasy|novel|children(?:\x{2019}|\x{0027})?s literature)\b/u', $classificationText) === 1) {
            $score += 50;
        }

        return $score;
    }

    private function canonicalKey(Literature $literature): string
    {
        if ($literature->sourceMapping?->canonical_work_id !== null) {
            return 'canonical:'.$literature->sourceMapping->canonical_work_id;
        }

        $author = $literature->authors->first()?->name ?? '';

        return $this->normalize($literature->displayTitle())
            .'|'.$this->normalize($author);
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }
}
