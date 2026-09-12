<?php

namespace App\Services\Profile;

use App\Models\ReadingList;
use App\Models\Review;
use App\Models\User;
use App\Services\Literature\CanonicalWorkIdentity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class UserStatsService
{
    public function __construct(private readonly CanonicalWorkIdentity $identity) {}

    /** @return array<string, mixed> */
    public function forUser(User $user, ?int $year = null): array
    {
        $year ??= now()->year;
        $user->loadMissing([
            'readingLists.logs',
            'readingLists.literature.authors',
            'readingLists.literature.categories',
            'readingLists.literature.sourceMapping',
            'reviews' => fn ($query) => $query->whereNull('hidden_at'),
            'reviews.literature.sourceMapping',
        ]);

        $readingByWork = $user->readingLists
            ->groupBy(fn (ReadingList $entry): string => $this->identity->key($entry->literature));
        $completed = $readingByWork
            ->filter(fn (Collection $entries): bool => $entries->contains('status', 'completed'))
            ->map(fn (Collection $entries): ReadingList => $entries
                ->where('status', 'completed')
                ->sortByDesc(fn (ReadingList $entry): int => $entry->completed_at?->getTimestamp() ?? 0)
                ->first());
        $readlist = $readingByWork->filter(
            fn (Collection $entries): bool => ! $entries->contains('status', 'completed')
                && $entries->contains('status', 'want_to_read'),
        );
        $reviews = $user->reviews
            ->sortByDesc('updated_at')
            ->unique(fn (Review $review): string => $this->identity->key($review->literature))
            ->values();
        $activityDates = $user->readingLists
            ->flatMap(fn (ReadingList $entry): Collection => $entry->logs->pluck('occurred_at'))
            ->merge($user->readingLists->pluck('started_at'))
            ->merge($user->readingLists->pluck('completed_at'))
            ->merge($reviews->pluck('updated_at'))
            ->filter()
            ->map(fn ($date): string => Carbon::parse($date)->toDateString())
            ->unique();

        return [
            'year' => $year,
            'summary' => [
                'completed' => $completed->count(),
                'readlist' => $readlist->count(),
                'reviews' => $reviews->count(),
                'average_rating' => $reviews->isEmpty() ? null : round((float) $reviews->avg('rating'), 2),
                'completed_this_year' => $completed->filter(
                    fn (ReadingList $entry): bool => $entry->completed_at?->year === $year,
                )->count(),
                'active_days' => $activityDates->count(),
            ],
            'rating_distribution' => collect(range(1, 10))->map(function (int $halfStar) use ($reviews): array {
                $rating = $halfStar / 2;

                return [
                    'rating' => $rating,
                    'label' => number_format($rating, 1),
                    'count' => $reviews->where('rating', $rating)->count(),
                ];
            })->all(),
            'by_type' => $this->rankedCounts(
                $completed->map(fn (ReadingList $entry): string => $entry->literature->typeLabel()),
            ),
            'top_genres' => $this->rankedCounts(
                $completed->flatMap(fn (ReadingList $entry): Collection => $entry->literature->categories->pluck('name')),
                8,
            ),
            'top_authors' => $this->rankedCounts(
                $completed->flatMap(fn (ReadingList $entry): Collection => $entry->literature->authors->pluck('name')),
                8,
            ),
            'completed_by_month' => collect(range(1, 12))->map(fn (int $month): array => [
                'month' => Carbon::create($year, $month)->format('M'),
                'count' => $completed->filter(fn (ReadingList $entry): bool => $entry->completed_at?->year === $year
                    && $entry->completed_at?->month === $month)->count(),
            ])->all(),
            'activity_by_year' => $activityDates
                ->countBy(fn (string $date): int => Carbon::parse($date)->year)
                ->sortKeys()
                ->map(fn (int $count, int|string $activityYear): array => [
                    'year' => (int) $activityYear,
                    'days' => $count,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, string>  $values
     * @return array<int, array{label: string, count: int}>
     */
    private function rankedCounts(Collection $values, ?int $limit = null): array
    {
        $counts = $values->filter()->countBy()->sortDesc();

        if ($limit !== null) {
            $counts = $counts->take($limit);
        }

        return $counts
            ->map(fn (int $count, string $label): array => ['label' => $label, 'count' => $count])
            ->values()
            ->all();
    }
}
