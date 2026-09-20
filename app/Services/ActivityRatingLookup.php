<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Review;
use Illuminate\Support\Collection;

class ActivityRatingLookup
{
    /**
     * @param  Collection<int, Activity>  $activities
     * @return Collection<string, float>
     */
    public function forActivities(Collection $activities): Collection
    {
        if ($activities->isEmpty()) {
            return collect();
        }

        return Review::query()
            ->whereNull('hidden_at')
            ->whereIn('user_id', $activities->pluck('user_id')->unique())
            ->whereIn('literature_id', $activities->pluck('literature_id')->unique())
            ->get(['user_id', 'literature_id', 'rating'])
            ->mapWithKeys(fn (Review $review): array => [
                $this->key($review->user_id, $review->literature_id) => (float) $review->rating,
            ]);
    }

    /** @param Collection<string, float> $ratings */
    public function forActivity(Activity $activity, Collection $ratings): ?float
    {
        $activityRating = data_get($activity->metadata, 'rating');

        if ($activityRating !== null) {
            return (float) $activityRating;
        }

        $rating = $ratings->get($this->key($activity->user_id, $activity->literature_id));

        return $rating === null ? null : (float) $rating;
    }

    private function key(int $userId, int $literatureId): string
    {
        return $userId.':'.$literatureId;
    }
}
