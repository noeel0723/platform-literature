<?php

namespace App\Models;

use Database\Factories\CustomListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['user_id', 'title', 'slug', 'description', 'is_private', 'is_ranked'])]
class CustomList extends Model
{
    /** @use HasFactory<CustomListFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CustomListItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CustomListItem::class)->orderBy('position');
    }

    /** @return HasMany<CustomListComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(CustomListComment::class);
    }

    /** @return MorphMany<Like, $this> */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    protected static function booted(): void
    {
        static::deleting(fn (CustomList $list) => $list->likes()->delete());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'is_ranked' => 'boolean',
        ];
    }
}
