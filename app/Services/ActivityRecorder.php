<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Discussion;
use App\Models\Literature;
use App\Models\Review;
use App\Models\User;
use App\Services\Literature\CanonicalWorkIdentity;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class ActivityRecorder
{
    public function __construct(private readonly CanonicalWorkIdentity $canonicalIdentity) {}

    public function recordReadingStatus(
        User $user,
        Literature $literature,
        ?string $previousStatus,
        string $currentStatus,
        bool $isReread = false,
        ?CarbonInterface $occurredAt = null,
        bool $forceCompletedActivity = false,
    ): ?Activity {
        $literature = $this->canonicalIdentity->representative($literature);
        $equivalentIds = $this->canonicalIdentity->equivalentLiteratureIds($literature);

        if (! $isReread && $previousStatus === 'completed' && $currentStatus !== 'completed') {
            Activity::query()
                ->whereBelongsTo($user)
                ->whereIn('literature_id', $equivalentIds)
                ->where('type', Activity::TYPE_COMPLETED)
                ->delete();
        }

        $type = match (true) {
            $isReread => Activity::TYPE_STARTED_READING,
            $forceCompletedActivity && $currentStatus === 'completed' => Activity::TYPE_COMPLETED,
            $previousStatus === $currentStatus => null,
            $currentStatus === 'want_to_read' => Activity::TYPE_ADDED_TO_READLIST,
            $currentStatus === 'reading' => Activity::TYPE_STARTED_READING,
            $currentStatus === 'completed' => Activity::TYPE_COMPLETED,
            default => null,
        };

        return $type === null ? null : Activity::query()->create([
            'user_id' => $user->id,
            'literature_id' => $literature->id,
            'review_id' => null,
            'type' => $type,
            'metadata' => $isReread ? ['reread' => true] : null,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    public function removeReadingStatus(User $user, Literature $literature): void
    {
        $literatureIds = $this->canonicalIdentity->equivalentLiteratureIds($literature);

        Activity::query()
            ->whereBelongsTo($user)
            ->whereIn('literature_id', $literatureIds)
            ->whereIn('type', [
                Activity::TYPE_STARTED_READING,
                Activity::TYPE_COMPLETED,
                Activity::TYPE_ADDED_TO_READLIST,
            ])
            ->delete();
    }

    public function recordReview(Review $review): ?Activity
    {
        $representative = $this->canonicalIdentity->representative($review->literature);
        Activity::query()
            ->whereBelongsTo($review)
            ->update(['literature_id' => $representative->id]);
        $body = filled($review->body) ? trim((string) $review->body) : null;

        if ($body === null) {
            Activity::query()
                ->whereBelongsTo($review)
                ->whereIn('type', [Activity::TYPE_RATED, Activity::TYPE_REVIEWED])
                ->delete();

            return null;
        }

        return Activity::query()->create([
            'user_id' => $review->user_id,
            'literature_id' => $representative->id,
            'review_id' => $review->id,
            'type' => Activity::TYPE_REVIEWED,
            'metadata' => [
                'rating' => (float) $review->rating,
                'review_excerpt' => Str::limit($body, 280),
                'contains_spoiler' => (bool) $review->contains_spoiler,
            ],
            'occurred_at' => now(),
        ]);
    }

    public function recordDiscussion(Discussion $discussion): Activity
    {
        $representative = $this->canonicalIdentity->representative($discussion->literature);

        return Activity::query()->create([
            'user_id' => $discussion->user_id,
            'literature_id' => $representative->id,
            'discussion_id' => $discussion->id,
            'type' => Activity::TYPE_DISCUSSION,
            'metadata' => [
                'title' => $discussion->title,
                'excerpt' => Str::limit(trim($discussion->body), 280),
                'contains_spoiler' => (bool) $discussion->contains_spoiler,
            ],
            'occurred_at' => now(),
        ]);
    }

    public function recordComment(Comment $comment): Activity
    {
        $discussion = $comment->discussion;
        $representative = $this->canonicalIdentity->representative($discussion->literature);

        return Activity::query()->create([
            'user_id' => $comment->user_id,
            'literature_id' => $representative->id,
            'discussion_id' => $discussion->id,
            'comment_id' => $comment->id,
            'type' => Activity::TYPE_COMMENT,
            'metadata' => [
                'discussion_title' => $discussion->title,
                'excerpt' => Str::limit(trim($comment->body), 280),
                'contains_spoiler' => (bool) $comment->contains_spoiler,
                'is_reply' => $comment->parent_id !== null,
            ],
            'occurred_at' => now(),
        ]);
    }
}
