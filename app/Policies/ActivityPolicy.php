<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ActivityPolicy
{
    public function view(User $viewer, Activity $activity): bool
    {
        $actor = $activity->user;
        if (! $viewer->canUseInteractiveFeatures() || ! $actor->isActive()) {
            return false;
        }
        if ($viewer->is($actor)) {
            return true;
        }
        if ($viewer->hasBlocked($actor) || $actor->hasBlocked($viewer)) {
            return false;
        }
        if ($viewer->isFollowing($actor)) {
            return true;
        }

        // A mutual friend's Following stream can also surface this reader's activity.
        return $viewer->following()->whereNull('deactivated_at')
            ->whereHas('following', fn (Builder $following) => $following->whereKey($viewer->id))
            ->whereHas('following', fn (Builder $following) => $following->whereKey($actor->id))
            ->exists();
    }
}
