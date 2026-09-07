<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Discussion;
use App\Models\Literature;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Str;

class ActivityRecorder
{
    public function recordReadingStatus(
        User $user,
        Literature $literature,
        ?string $previousStatus,
        string $currentStatus,
        bool $isReread = false,
    ): ?Activity {
        $type = match (true) {
            $isReread => Activity::TYPE_STARTED_READING,
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
            'occurred_at' => now(),
        ]);
    }

    public function recordReview(Review $review): Activity
    {
        $body = filled($review->body) ? trim((string) $review->body) : null;

        return Activity::query()->create([
            'user_id' => $review->user_id,
            'literature_id' => $review->literature_id,
            'review_id' => $review->id,
            'type' => $body === null ? Activity::TYPE_RATED : Activity::TYPE_REVIEWED,
            'metadata' => [
                'rating' => (float) $review->rating,
                'review_excerpt' => $body === null ? null : Str::limit($body, 280),
                'contains_spoiler' => (bool) $review->contains_spoiler,
            ],
            'occurred_at' => now(),
        ]);
    }

    public function recordDiscussion(Discussion $discussion): Activity
    {
        return Activity::query()->create([
            'user_id' => $discussion->user_id,
            'literature_id' => $discussion->literature_id,
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

        return Activity::query()->create([
            'user_id' => $comment->user_id,
            'literature_id' => $discussion->literature_id,
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
