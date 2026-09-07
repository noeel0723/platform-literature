<?php

namespace App\Models;

use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'literature_id', 'review_id', 'discussion_id', 'comment_id', 'type', 'metadata', 'occurred_at'])]
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    public const TYPE_STARTED_READING = 'started_reading';

    public const TYPE_COMPLETED = 'completed';

    public const TYPE_RATED = 'rated';

    public const TYPE_REVIEWED = 'reviewed';

    public const TYPE_ADDED_TO_READLIST = 'added_to_readlist';

    public const TYPE_DISCUSSION = 'discussion';

    public const TYPE_COMMENT = 'comment';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Literature, $this> */
    public function literature(): BelongsTo
    {
        return $this->belongsTo(Literature::class);
    }

    /** @return BelongsTo<Review, $this> */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /** @return BelongsTo<Discussion, $this> */
    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class);
    }

    /** @return BelongsTo<Comment, $this> */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    /** @param Builder<Activity> $query */
    public function scopeVisibleToReaders(Builder $query): Builder
    {
        return $query
            ->whereHas('user', fn (Builder $users) => $users->whereNull('deactivated_at'))
            ->where(function (Builder $visibility): void {
                $visibility
                    ->whereIn('type', [
                        self::TYPE_STARTED_READING,
                        self::TYPE_COMPLETED,
                        self::TYPE_ADDED_TO_READLIST,
                    ])
                    ->orWhere(function (Builder $reviews): void {
                        $reviews
                            ->whereIn('type', [self::TYPE_RATED, self::TYPE_REVIEWED])
                            ->whereHas('review', fn (Builder $review) => $review->whereNull('hidden_at'));
                    })
                    ->orWhere(function (Builder $discussions): void {
                        $discussions
                            ->where('type', self::TYPE_DISCUSSION)
                            ->whereHas('discussion', fn (Builder $discussion) => $discussion->whereNull('hidden_at'));
                    })
                    ->orWhere(function (Builder $comments): void {
                        $comments
                            ->where('type', self::TYPE_COMMENT)
                            ->whereHas('comment', function (Builder $comment): void {
                                $comment
                                    ->whereNull('hidden_at')
                                    ->whereHas('discussion', fn (Builder $discussion) => $discussion->whereNull('hidden_at'));
                            });
                    });
            });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
