<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Report;
use App\Services\ActivityRatingLookup;
use App\Services\Literature\CanonicalWorkIdentity;
use App\Support\LiteraturePreviewData;
use App\Support\ProfilePagePresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ActivityDetailController extends Controller
{
    public function __invoke(Request $request, Activity $activity, LiteraturePreviewData $previewData, ProfilePagePresenter $presenter, CanonicalWorkIdentity $identity, ActivityRatingLookup $ratings): Response
    {
        abort_unless(Activity::query()->visibleToReaders()->whereKey($activity->id)->exists(), 404);
        $activity->loadMissing(['user', 'literature.authors', 'literature.metadataOverride', 'literature.sourceMapping.canonicalWork.metadataOverride', 'review', 'discussion', 'comment.discussion']);
        Gate::authorize('view', $activity);
        $viewer = $request->user();
        $literature = $identity->representative($activity->literature);
        $literature->loadMissing(['authors', 'metadataOverride', 'sourceMapping.canonicalWork.metadataOverride']);
        $equivalentIds = $identity->equivalentLiteratureIds($literature);
        $reading = $activity->user->readingLists()->whereIn('literature_id', $equivalentIds)->latest('updated_at')->first();
        $review = $activity->type === Activity::TYPE_REVIEWED ? $activity->review : null;
        $discussion = $activity->type === Activity::TYPE_DISCUSSION ? $activity->discussion : $activity->comment?->discussion;
        $content = match ($activity->type) {
            Activity::TYPE_REVIEWED => $review,
            Activity::TYPE_DISCUSSION => $discussion,
            Activity::TYPE_COMMENT => $activity->comment,
            default => null,
        };
        if ($content !== null) {
            abort_unless($content->user_id === $activity->user_id, 404);
            $contentLiteratureId = $activity->type === Activity::TYPE_COMMENT ? $discussion?->literature_id : $content->literature_id;
            abort_unless($equivalentIds->contains($contentLiteratureId), 404);
        }
        $review?->loadCount('likes');
        $preview = $previewData->present($literature, $viewer);
        $preview['literature']['review_url'] = route('reviews.update', [$literature, 'activity' => $activity->id]);
        $actor = $presenter->user($activity->user);
        $actor['initial'] = $actor['initials'];
        $actorActivityUrl = $viewer->is($activity->user) ? route('activity.index', ['scope' => 'you'])
            : ($viewer->isFollowing($activity->user) && $activity->user->isFollowing($viewer) ? route('profiles.activity', $activity->user) : null);

        return Inertia::render('Activity/Show', [
            'activity' => [
                'id' => $activity->id,
                'url' => route('activity.show', $activity),
                'type' => $activity->type,
                'label' => match ($activity->type) {
                    Activity::TYPE_REVIEWED => 'Review',
                    Activity::TYPE_COMPLETED => 'Completed',
                    Activity::TYPE_STARTED_READING => 'Started reading',
                    Activity::TYPE_ADDED_TO_READLIST => 'Added to Readlist',
                    Activity::TYPE_DISCUSSION => 'Discussion',
                    Activity::TYPE_COMMENT => 'Comment',
                    default => 'Reading activity',
                },
                'user' => $actor,
                'is_owner' => $viewer->is($activity->user),
                'occurred_at' => $activity->occurred_at->utc()->toIso8601String(),
                'completed_at' => in_array($activity->type, [Activity::TYPE_REVIEWED, Activity::TYPE_COMPLETED], true) ? $reading?->completed_at?->toDateString() : null,
                'rating' => $review?->rating ?? $ratings->forActivity($activity, $ratings->forActivities($activity->newCollection([$activity]))),
                'body' => $content?->body,
                'contains_spoiler' => (bool) ($content?->contains_spoiler || $discussion?->contains_spoiler),
                'discussion_title' => $discussion?->title,
                'discussion_url' => $discussion === null ? null : route('literatures.show', $literature).'#discussion-'.$discussion->id,
                'review' => $review === null ? null : [
                    'likes_count' => $review->likes_count,
                    'is_liked' => $review->likes()->where('user_id', $viewer->id)->exists(),
                    'like_url' => route('reviews.likes.store', $review),
                    'unlike_url' => route('reviews.likes.destroy', $review),
                ],
                'report' => $content === null || $content->user_id === $viewer->id ? null : [
                    'type' => match ($activity->type) {
                        Activity::TYPE_REVIEWED => 'review', Activity::TYPE_DISCUSSION => 'discussion', default => 'comment'
                    },
                    'id' => $content->id,
                ],
            ],
            'literature' => [...$presenter->literature($literature), 'id' => $literature->id],
            'preview' => $preview,
            'routes' => [
                'feed' => route('activity.index'),
                'own_activity' => route('activity.index', ['scope' => 'you']),
                'actor_activity' => $actorActivityUrl,
                'report' => route('reports.store'),
            ],
            'reportReasons' => Report::REASON_LABELS,
        ]);
    }
}
