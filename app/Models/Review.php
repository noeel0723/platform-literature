<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['user_id', 'literature_id', 'rating', 'body', 'contains_spoiler', 'hidden_at', 'hidden_by'])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

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

    /** @return MorphMany<Like, $this> */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    /** @return MorphMany<Report, $this> */
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    protected static function booted(): void
    {
        static::deleting(function (Review $review): void {
            $review->likes()->delete();
            $review->reports()->delete();
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rating' => 'float',
            'contains_spoiler' => 'boolean',
            'hidden_at' => 'datetime',
        ];
    }
}
