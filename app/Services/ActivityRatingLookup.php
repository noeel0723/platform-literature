<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Literature;
use App\Models\Review;
use App\Services\Literature\CanonicalWorkIdentity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ActivityRatingLookup
{
    public function __construct(private readonly CanonicalWorkIdentity $identity) {}

    /**
     * @param  Collection<int, Activity>  $activities
     * @return Collection<string, float>
     */
    public function forActivities(Collection $activities): Collection
    {
        if ($activities->isEmpty()) {
            return collect();
        }

        $activities->loadMissing('literature.sourceMapping');
        $canonicalIds = $activities->pluck('literature.sourceMapping.canonical_work_id')->filter()->unique();
        $literatureIds = $activities->pluck('literature_id')->unique();

        return Review::query()
            ->whereNull('hidden_at')
            ->whereIn('user_id', $activities->pluck('user_id')->unique())
            ->where(function (Builder $query) use ($literatureIds, $canonicalIds): void {
                $query->whereIn('literature_id', $literatureIds);

                if ($canonicalIds->isNotEmpty()) {
                    $query->orWhereHas('literature.sourceMapping', fn (Builder $mapping) => $mapping
                        ->whereIn('canonical_work_id', $canonicalIds));
                }
            })
            ->with('literature.sourceMapping')
            ->latest('updated_at')
            ->get(['id', 'user_id', 'literature_id', 'rating', 'updated_at'])
            ->unique(fn (Review $review): string => $this->key($review->user_id, $review->literature))
            ->mapWithKeys(fn (Review $review): array => [
                $this->key($review->user_id, $review->literature) => (float) $review->rating,
            ]);
    }

    /** @param Collection<string, float> $ratings */
    public function forActivity(Activity $activity, Collection $ratings): ?float
    {
        $activityRating = data_get($activity->metadata, 'rating');

        if ($activityRating !== null) {
            return (float) $activityRating;
        }

        $rating = $ratings->get($this->key($activity->user_id, $activity->literature));

        return $rating === null ? null : (float) $rating;
    }

    private function key(int $userId, Literature $literature): string
    {
        return $userId.':'.$this->identity->key($literature);
    }
}
