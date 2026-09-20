<?php

namespace App\Services\Literature;

use App\Models\Activity;
use App\Models\Category;
use App\Models\Literature;
use Illuminate\Database\Eloquent\Builder;
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

    private const RATINGS = [4.5, 4.0, 3.5, 3.0];

    public function __construct(
        private readonly CanonicalLiteratureSearch $canonicalSearch,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{decade: ?int, rating: ?float, genre: ?string, sort: string}
     */
    public function normalizeFilters(array $input): array
    {
        $decade = filter_var($input['decade'] ?? null, FILTER_VALIDATE_INT);
        $decade = $decade !== false && $decade >= 0 && $decade <= 3000 && $decade % 10 === 0
            ? $decade
            : null;
        $rating = is_numeric($input['rating'] ?? null) ? (float) $input['rating'] : null;
        $rating = in_array($rating, self::RATINGS, true) ? $rating : null;
        $genre = Str::slug(trim((string) ($input['genre'] ?? '')));
        $sort = in_array($input['sort'] ?? null, self::SORTS, true)
            ? (string) $input['sort']
            : 'popularity';

        return [
            'decade' => $decade,
            'rating' => $rating,
            'genre' => $genre === '' ? null : $genre,
            'sort' => $sort,
        ];
    }

    /** @return LengthAwarePaginator<Literature> */
    public function browse(array $filters, int $perPage = 48): LengthAwarePaginator
    {
        $query = $this->queryWithMetrics();

        if ($filters['decade'] !== null) {
            $query->whereBetween('literatures.publication_year', [
                $filters['decade'],
                $filters['decade'] + 9,
            ]);
        }

        if ($filters['rating'] !== null) {
            $query->whereRaw(
                'browse_ratings.average_rating * 2 >= ?',
                [(int) round($filters['rating'] * 2)],
            );
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

        $this->applySort($query, $filters['sort']);

        return $query->paginate($perPage);
    }

    /** @return Collection<int, Literature> */
    public function popular(int $limit = 4): Collection
    {
        $query = $this->queryWithMetrics();
        $this->applySort($query, 'popularity');

        return $query->limit($limit)->get();
    }

    /**
     * @return array{
     *     decades: list<array{value: int, label: string}>,
     *     ratings: list<array{value: float, label: string}>,
     *     genres: list<array{name: string, slug: string}>,
     *     sorts: list<array{value: string, label: string, group: string}>
     * }
     */
    public function options(): array
    {
        $decades = $this->canonicalSearch->query()
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
            'ratings' => collect(self::RATINGS)->map(fn (float $rating): array => [
                'value' => $rating,
                'label' => number_format($rating, 1).'+',
            ])->all(),
            'genres' => $genres,
            'sorts' => [
                ['value' => 'popularity', 'label' => 'Popularity', 'group' => 'Popularity'],
                ['value' => 'year-desc', 'label' => 'Newest First', 'group' => 'Release Date'],
                ['value' => 'year-asc', 'label' => 'Oldest First', 'group' => 'Release Date'],
                ['value' => 'rating-desc', 'label' => 'Highest First', 'group' => 'Average Rating'],
                ['value' => 'rating-asc', 'label' => 'Lowest First', 'group' => 'Average Rating'],
            ],
        ];
    }

    /** @return Builder<Literature> */
    private function queryWithMetrics(): Builder
    {
        $query = $this->canonicalSearch->query()
            ->with('sourceMapping.canonicalWork.literatures.categories');

        return $query
            ->leftJoinSub($this->ratingStats(), 'browse_ratings', 'browse_ratings.representative_id', '=', 'literatures.id')
            ->leftJoinSub($this->readingStats(), 'browse_reading', 'browse_reading.representative_id', '=', 'literatures.id')
            ->leftJoinSub($this->favoriteStats(), 'browse_favorites', 'browse_favorites.representative_id', '=', 'literatures.id')
            ->leftJoinSub($this->weeklyActivityStats(), 'browse_weekly_activity', 'browse_weekly_activity.representative_id', '=', 'literatures.id')
            ->select('literatures.*')
            ->selectRaw('browse_ratings.average_rating AS average_rating')
            ->selectRaw('COALESCE(browse_ratings.rating_count, 0) AS rating_count')
            ->selectRaw('(COALESCE(browse_weekly_activity.score, 0) + COALESCE(browse_favorites.weekly_score, 0)) AS weekly_popularity_score')
            ->selectRaw('(COALESCE(browse_reading.score, 0) + COALESCE(browse_ratings.popularity_score, 0) + COALESCE(browse_favorites.overall_score, 0)) AS overall_popularity_score');
    }

    private function ratingStats(): \Illuminate\Database\Query\Builder
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

        return DB::query()
            ->fromSub($ratingsByReader, 'canonical_reader_ratings')
            ->select('representative_id')
            ->selectRaw('AVG(reader_rating) AS average_rating')
            ->selectRaw('COUNT(*) AS rating_count')
            ->selectRaw('SUM(CASE WHEN reader_rating >= 4 THEN 2 ELSE 0 END) AS popularity_score')
            ->groupBy('representative_id');
    }

    private function readingStats(): \Illuminate\Database\Query\Builder
    {
        $representative = 'COALESCE(canonical_works.preferred_literature_id, browse_reading_lists.literature_id)';

        return DB::table('reading_lists as browse_reading_lists')
            ->leftJoin('literature_source_mappings', 'literature_source_mappings.literature_id', '=', 'browse_reading_lists.literature_id')
            ->leftJoin('canonical_works', 'canonical_works.id', '=', 'literature_source_mappings.canonical_work_id')
            ->whereIn('browse_reading_lists.status', ['reading', 'completed'])
            ->selectRaw($representative.' AS representative_id')
            ->selectRaw('COUNT(DISTINCT browse_reading_lists.user_id) * 2 AS score')
            ->groupByRaw($representative);
    }

    private function favoriteStats(): \Illuminate\Database\Query\Builder
    {
        $representative = 'COALESCE(canonical_works.preferred_literature_id, browse_favorites.literature_id)';

        return DB::table('user_favorite_literatures as browse_favorites')
            ->leftJoin('literature_source_mappings', 'literature_source_mappings.literature_id', '=', 'browse_favorites.literature_id')
            ->leftJoin('canonical_works', 'canonical_works.id', '=', 'literature_source_mappings.canonical_work_id')
            ->selectRaw($representative.' AS representative_id')
            ->selectRaw('COUNT(DISTINCT browse_favorites.user_id) * 3 AS overall_score')
            ->selectRaw('COUNT(DISTINCT CASE WHEN browse_favorites.created_at >= ? THEN browse_favorites.user_id END) * 3 AS weekly_score', [now()->subDays(7)])
            ->groupByRaw($representative);
    }

    private function weeklyActivityStats(): \Illuminate\Database\Query\Builder
    {
        $representative = 'COALESCE(canonical_works.preferred_literature_id, browse_activities.literature_id)';
        $signals = DB::table('activities as browse_activities')
            ->join('users as activity_users', 'activity_users.id', '=', 'browse_activities.user_id')
            ->leftJoin('literature_source_mappings', 'literature_source_mappings.literature_id', '=', 'browse_activities.literature_id')
            ->leftJoin('canonical_works', 'canonical_works.id', '=', 'literature_source_mappings.canonical_work_id')
            ->whereNull('activity_users.deactivated_at')
            ->where('browse_activities.occurred_at', '>=', now()->subDays(7))
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
                            ->whereIn('browse_activities.type', [Activity::TYPE_RATED, Activity::TYPE_REVIEWED])
                            ->whereExists(function ($review): void {
                                $review
                                    ->selectRaw('1')
                                    ->from('reviews')
                                    ->whereColumn('reviews.id', 'browse_activities.review_id')
                                    ->whereNull('reviews.hidden_at');
                            });
                    });
            })
            ->selectRaw($representative.' AS representative_id')
            ->addSelect(['browse_activities.user_id', 'browse_activities.type'])
            ->groupByRaw($representative)
            ->groupBy('browse_activities.user_id', 'browse_activities.type');

        return DB::query()
            ->fromSub($signals, 'canonical_weekly_signals')
            ->select('representative_id')
            ->selectRaw("SUM(CASE type WHEN 'reviewed' THEN 4 WHEN 'rated' THEN 3 WHEN 'completed' THEN 2 WHEN 'started_reading' THEN 1 ELSE 0 END) AS score")
            ->groupBy('representative_id');
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
