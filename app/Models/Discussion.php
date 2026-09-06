<?php

namespace App\Models;

use Database\Factories\DiscussionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'literature_id', 'title', 'body', 'contains_spoiler'])]
class Discussion extends Model
{
    /** @use HasFactory<DiscussionFactory> */
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

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return HasMany<Comment, $this> */
    public function topLevelComments(): HasMany
    {
        return $this->hasMany(Comment::class)
            ->whereNull('parent_id')
            ->oldest();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'contains_spoiler' => 'boolean',
        ];
    }
}
