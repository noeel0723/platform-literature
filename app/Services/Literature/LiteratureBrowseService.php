<?php

namespace App\Services\Literature;

use App\Models\Activity;
use App\Models\Category;
use App\Models\Literature;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LiteratureBrowseService
{
    private const SORTS = [
        'popularity',
        'year-desc',
        'year-asc',
        'rating-desc',
        'rating-asc',
    ];

    private const RATING_FILTERS = [
        'highest',
        'lowest',
        'top-100',
    ];

    public function __construct(
        private readonly CanonicalLiteratureSearch $canonicalSearch,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{q: string, decade: ?int, rating: ?string, genre: ?string, sort: string}
     */
    public function normalizeFilters(array $input): array
    {
        $search = Str::limit(trim((string) ($input['q'] ?? '')), 100, '');
        $decade = filter_var($input['decade'] ?? null, FILTER_VALIDATE_INT);
        $decade = $decade !== false && $decade >= 0 && $decade <= 3000 && $decade % 10 === 0
            ? $decade
            : null;
        $rating = in_array($input['rating'] ?? null, self::RATING_FILTERS, true)
            ? (string) $input['rating']
            : null;
        $genre = Str::slug(trim((string) ($input['genre'] ?? '')));
        $sort = in_array($input['sort'] ?? null, self::SORTS, true)
            ? (string) $input['sort']
            : 'popularity';

        return [
            'q' => $search,
            'decade' => $decade,
            'rating' => $rating,
            'genre' => $genre === '' ? null : $genre,
            'sort' => $sort,
        ];
    }

    /** @return LengthAwarePaginator<Literature> */
    public function browse(array $filters, int $perPage = 48): LengthAwarePaginator
    {
        $sort = match ($filters['rating']) {
            'highest', 'top-100' => 'rating-desc',
            'lowest' => 'rating-asc',
            default => $filters['sort'],
        };
        $base = $this->canonicalSearch->representativeQuery($filters['q']);
        $ratingsJoined = $filters['rating'] !== null;

        if ($ratingsJoined) {
            $base->leftJoinSub($this->ratingStats(), 'browse_ratings', 'browse_ratings.representative_id', '=', 'literatures.id');
        }

        $this->applyFilters($base, $filters);
        $countQuery = clone $base;
        $yearSort = in_array($sort, ['year-desc', 'year-asc'], true);
        $query = $yearSort
            ? $base->select('literatures.id')
            : $this->queryWithMetrics(base: $base, ratingsJoined: $ratingsJoined);
        $this->applySort($query, $sort);
        $page = $query->paginate($perPage, ['literatures.id'], total: fn (): int => $countQuery->count('literatures.id'));

        $scores = $page->getCollection();

        if ($yearSort && $scores->isNotEmpty()) {
            // Date ordering needs no community aggregation until the page IDs
            // are known. Scope the display metrics to these works and siblings.
            $metrics = $this->queryWithMetrics(representativeIds: $scores->modelKeys())->get()->keyBy('id');
            $scores = $scores->map(fn (Literature $literature): ?Literature => $metrics->get($literature->id))->filter()->values();
        }

        return $page->setCollection($this->hydrateResults($scores));
    }

    /** @param Builder<Literature> $query */
    private function applyFilters(Builder $query, array $filters): void
    {

        if ($filters['decade'] !== null) {
            $query->whereBetween('literatures.publication_year', [
                $filters['decade'],
                $filters['decade'] + 9,
            ]);
        }

        if ($filters['rating'] !== null) {
            $query->whereNotNull('browse_ratings.average_rating');

            if ($filters['rating'] === 'top-100') {
                $topRatedIds = DB::query()
                    ->fromSub($this->ratingStats(), 'top_ratings')
                    ->select('representative_id')
                    ->orderByDesc('average_rating')
                    ->orderByDesc('rating_count')
                    ->limit(100);

                $query->joinSub(
                    $topRatedIds,
                    'top_100_ratings',
                    'top_100_ratings.representative_id',
                    '=',
                    'literatures.id',
                );
            }
        }

        if ($filters['genre'] !== null) {
            $category = Category::query()->where('slug', $filters['genre'])->first();

            if ($category === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function (Builder $literatures) use ($category): void {
                    $literatures
                        ->whereHas('categories', fn (Builder $categories) => $categories->whereKey($category->id))
                        ->orWhereHas(
                            'sourceMapping.canonicalWork.literatures.categories',
                            fn (Builder $categories) => $categories->whereKey($category->id),
                        );
                });
            }
        }

    }

    /**
     * Keep ranking live: a shared TTL cache could retain expired seven-day signals
     * or delay new activity/moderation. Cache only with complete invalidation and
     * an expiry tied to the next signal leaving the rolling window.
     *
     * @param  list<string>  $formats
     * @return Collection<int, Literature>
     */
    public function popular(int $limit = 4, array $formats = []): Collection
    {
        $base = $this->canonicalSearch->representativeQuery()
            ->when($formats !== [], fn (Builder $query) => $query->whereIn('literatures.type', $formats));
        $query = $this->queryWithMetrics(base: $base);
        $this->applySort($query, 'popularity');

        return $this->hydrateResults($query->limit($limit)->get());
    }

    /**
     * Only hydrate display metadata for the selected page, never for every row
     * participating in the aggregate sort or the paginator's total count.
     *
     * @param  Collection<int, Literature>  $scores
     * @return Collection<int, Literature>
     */
    private function hydrateResults(Collection $scores): Collection
    {
        if ($scores->isEmpty()) {
            return collect();
        }

        $models = $this->canonicalSearch->query()
            ->whereKey($scores->pluck('id')->all())
            ->with('sourceMapping.canonicalWork.literatures.categories')
            ->get()->keyBy('id');

        return $scores->map(function (Literature $score) use ($models): ?Literature {
            $literature = $models->get($score->id);

            if ($literature !== null) {
                foreach (['average_rating', 'rating_count', 'weekly_popularity_score', 'overall_popularity_score'] as $metric) {
                    $literature->setAttribute($metric, $score->getAttribute($metric));
                }
            }

            return $literature;
        })->filter()->values();
    }

    /**
     * @return array{
     *     decades: list<array{value: int, label: string}>,
     *     ratings: list<array{value: string, label: string}>,
     *     genres: list<array{name: string, slug: string}>,
     *     sorts: list<array{value: string, label: string, group: string}>
     * }
     */
    public function options(): array
    {
        $decades = $this->canonicalSearch->representativeQuery()
            ->whereNotNull('literatures.publication_year')
            ->distinct()
            ->pluck('literatures.publication_year')
            ->map(fn (int $year): int => intdiv($year, 10) * 10)
            ->unique()
            ->sortDesc()
            ->values()
            ->map(fn (int $decade): array => [
                'value' => $decade,
                'label' => $decade.'s',
            ])
            ->all();
        $genres = Category::query()
            ->whereHas('literatures', function (Builder $literatures): void {
                $literatures
                    ->whereIn('type', Literature::supportedTypes())
                    ->where(function (Builder $eligible): void {
                        $eligible
                            ->whereDoesntHave('sourceMapping')
                            ->orWhereHas(
                                'sourceMapping.canonicalWork',
                                fn (Builder $works) => $works->whereNotNull('preferred_literature_id'),
                            );
                    });
            })
            ->orderBy('name')
            ->get(['name', 'slug'])
            ->unique('slug')
            ->values()
            ->map(fn (Category $category): array => [
                'name' => $category->name,
                'slug' => $category->slug,
            ])
            ->all();

        return [
            'decades' => $decades,
            'ratings' => [
                ['value' => 'highest', 'label' => 'Highest First'],
                ['value' => 'lowest', 'label' => 'Lowest First'],
                ['value' => 'top-100', 'label' => 'Top 100 Ratings'],
            ],
            'genres' => $genres,
            'sorts' => [
                ['value' => 'popularity', 'label' => 'Popularity', 'group' => 'Popularity'],
                ['value' => 'year-desc', 'label' => 'Newest First', 'group' => 'Release Date'],
                ['value' => 'year-asc', 'label' => 'Oldest First', 'group' => 'Release Date'],
            ],
        ];
    }

    /** @return Builder<Literature> */
    private function queryWithMetrics(string $search = '', ?Builder $base = null, bool $ratingsJoined = false, ?array $representativeIds = null): Builder
    {
        $query = $base ?? $this->canonicalSearch->representativeQuery($search);
        $windowStart = now()->subDays(7);

        if ($representativeIds !== null) {
            $query->whereKey($representativeIds);
        }

        if (! $ratingsJoined) {
            $query->leftJoinSub($this->ratingStats($representativeIds), 'browse_ratings', 'browse_ratings.representative_id', '=', 'literatures.id');
        }

        return $query
            ->leftJoinSub($this->readingStats($representativeIds), 'browse_reading', 'browse_reading.representative_id', '=', 'literatures.id')
            ->leftJoinSub($this->favoriteStats($representativeIds, $windowStart), 'browse_favorites', 'browse_favorites.representative_id', '=', 'literatures.id')
            ->leftJoinSub($this->weeklyActivityStats($representativeIds, $windowStart), 'browse_weekly_activity', 'browse_weekly_activity.representative_id', '=', 'literatures.id')
            ->select('literatures.id')
            ->selectRaw('browse_ratings.average_rating AS average_rating')
            ->selectRaw('COALESCE(browse_ratings.rating_count, 0) AS rating_count')
            ->selectRaw('(COALESCE(browse_weekly_activity.score, 0) + COALESCE(browse_favorites.weekly_score, 0)) AS weekly_popularity_score')
            ->selectRaw('(COALESCE(browse_reading.score, 0) + COALESCE(browse_ratings.popularity_score, 0) + COALESCE(browse_favorites.overall_score, 0)) AS overall_popularity_score');
    }

    private function ratingStats(?array $representativeIds = null): QueryBuilder
    {
        $representative = 'COALESCE(canonical_works.preferred_literature_id, browse_reviews.literature_id)';
        $ratingsByReader = DB::table('reviews as browse_reviews')
            ->leftJoin('literature_source_mappings', 'literature_source_mappings.literature_id', '=', 'browse_reviews.literature_id')
            ->leftJoin('canonical_works', 'canonical_works.id', '=', 'literature_source_mappings.canonical_work_id')
            ->whereNull('browse_reviews.hidden_at')
            ->selectRaw($representative.' AS representative_id')
            ->addSelect('browse_reviews.user_id')
            ->selectRaw('AVG(browse_reviews.rating) AS reader_rating')
            ->groupByRaw($representative)
            ->groupBy('browse_reviews.user_id');

        $this->scopeSignals($ratingsByReader, 'browse_reviews', $representativeIds);

        return DB::query()
            ->fromSub($ratingsByReader, 'canonical_reader_ratings')
            ->select('representative_id')
            ->selectRaw('AVG(reader_rating) AS average_rating')
            ->selectRaw('COUNT(*) AS rating_count')
            ->selectRaw('SUM(CASE WHEN reader_rating >= 4 THEN 2 ELSE 0 END) AS popularity_score')
            ->groupBy('representative_id');
    }

    private function readingStats(?array $representativeIds = null): QueryBuilder
    {
        $representative = 'COALESCE(canonical_works.preferred_literature_id, browse_reading_lists.literature_id)';

        $query = DB::table('reading_lists as browse_reading_lists')
            ->leftJoin('literature_source_mappings', 'literature_source_mappings.literature_id', '=', 'browse_reading_lists.literature_id')
            ->leftJoin('canonical_works', 'canonical_works.id', '=', 'literature_source_mappings.canonical_work_id')
            ->whereIn('browse_reading_lists.status', ['reading', 'completed'])
            ->selectRaw($representative.' AS representative_id')
            ->selectRaw('COUNT(DISTINCT browse_reading_lists.user_id) * 2 AS score')
            ->groupByRaw($representative);

        $this->scopeSignals($query, 'browse_reading_lists', $representativeIds);

        return $query;
    }

    private function favoriteStats(?array $representativeIds = null, ?CarbonInterface $windowStart = null): QueryBuilder
    {
        $representative = 'COALESCE(canonical_works.preferred_literature_id, browse_favorites.literature_id)';

        $query = DB::table('user_favorite_literatures as browse_favorites')
            ->leftJoin('literature_source_mappings', 'literature_source_mappings.literature_id', '=', 'browse_favorites.literature_id')
            ->leftJoin('canonical_works', 'canonical_works.id', '=', 'literature_source_mappings.canonical_work_id')
            ->selectRaw($representative.' AS representative_id')
            ->selectRaw('COUNT(DISTINCT browse_favorites.user_id) * 3 AS overall_score')
            ->selectRaw('COUNT(DISTINCT CASE WHEN browse_favorites.created_at >= ? THEN browse_favorites.user_id END) * 3 AS weekly_score', [$windowStart ?? now()->subDays(7)])
            ->groupByRaw($representative);

        $this->scopeSignals($query, 'browse_favorites', $representativeIds);

        return $query;
    }

    private function weeklyActivityStats(?array $representativeIds = null, ?CarbonInterface $windowStart = null): QueryBuilder
    {
        $representative = 'COALESCE(canonical_works.preferred_literature_id, browse_activities.literature_id)';
        $signals = DB::table('activities as browse_activities')
            ->join('users as activity_users', 'activity_users.id', '=', 'browse_activities.user_id')
            ->leftJoin('literature_source_mappings', 'literature_source_mappings.literature_id', '=', 'browse_activities.literature_id')
            ->leftJoin('canonical_works', 'canonical_works.id', '=', 'literature_source_mappings.canonical_work_id')
            ->whereNull('activity_users.deactivated_at')
            ->where('browse_activities.occurred_at', '>=', $windowStart ?? now()->subDays(7))
            ->where(function ($visible): void {
                $visible
                    ->where('browse_activities.type', Activity::TYPE_STARTED_READING)
                    ->orWhere(function ($completed): void {
                        $completed
                            ->where('browse_activities.type', Activity::TYPE_COMPLETED)
                            ->whereExists(function ($readingLists): void {
                                $readingLists
                                    ->selectRaw('1')
                                    ->from('reading_lists')
                                    ->whereColumn('reading_lists.user_id', 'browse_activities.user_id')
                                    ->whereColumn('reading_lists.literature_id', 'browse_activities.literature_id')
                                    ->where('reading_lists.status', 'completed');
                            });
                    })
                    ->orWhere(function ($reviews): void {
                        $reviews
                            ->where('browse_activities.type', Activity::TYPE_REVIEWED)
                            ->whereExists(function ($review): void {
                                $review
                                    ->selectRaw('1')
                                    ->from('reviews')
                                    ->whereColumn('reviews.id', 'browse_activities.review_id')
                                    ->whereNull('reviews.hidden_at')
                                    ->whereNotNull('reviews.body')
                                    ->whereRaw("TRIM(reviews.body) <> ''");
                            });
                    });
            })
            ->selectRaw($representative.' AS representative_id')
            ->addSelect(['browse_activities.user_id', 'browse_activities.type'])
            ->groupByRaw($representative)
            ->groupBy('browse_activities.user_id', 'browse_activities.type');

        $this->scopeSignals($signals, 'browse_activities', $representativeIds);

        return DB::query()
            ->fromSub($signals, 'canonical_weekly_signals')
            ->select('representative_id')
            ->selectRaw("SUM(CASE type WHEN 'reviewed' THEN 4 WHEN 'completed' THEN 2 WHEN 'started_reading' THEN 1 ELSE 0 END) AS score")
            ->groupBy('representative_id');
    }

    /** @param list<int>|null $representativeIds */
    private function scopeSignals(QueryBuilder $query, string $table, ?array $representativeIds): void
    {
        if ($representativeIds === null) {
            return;
        }

        $siblings = DB::table('literature_source_mappings as metric_siblings')
            ->join('canonical_works as metric_works', 'metric_works.id', '=', 'metric_siblings.canonical_work_id')
            ->whereIn('metric_works.preferred_literature_id', $representativeIds)
            ->select('metric_siblings.literature_id');
        $query->where(fn (QueryBuilder $signals) => $signals
            ->whereIn($table.'.literature_id', $representativeIds)
            ->orWhereIn($table.'.literature_id', $siblings));
    }

    /** @param Builder<Literature> $query */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'year-desc' => $query
                ->orderByRaw('literatures.publication_year IS NULL')
                ->orderByDesc('literatures.publication_year')
                ->orderByDesc('literatures.id'),
            'year-asc' => $query
                ->orderByRaw('literatures.publication_year IS NULL')
                ->orderBy('literatures.publication_year')
                ->orderBy('literatures.id'),
            'rating-desc' => $query
                ->orderByRaw('average_rating IS NULL')
                ->orderByDesc('average_rating')
                ->orderByDesc('rating_count')
                ->orderByDesc('literatures.id'),
            'rating-asc' => $query
                ->orderByRaw('average_rating IS NULL')
                ->orderBy('average_rating')
                ->orderByDesc('rating_count')
                ->orderBy('literatures.id'),
            default => $query
                ->orderByDesc('weekly_popularity_score')
                ->orderByDesc('overall_popularity_score')
                ->orderByDesc('average_rating')
                ->orderByDesc('literatures.updated_at')
                ->orderByDesc('literatures.id'),
        };
    }
}
