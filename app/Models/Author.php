<?php

namespace App\Models;

use Database\Factories\AuthorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'normalized_name', 'external_entity_id', 'slug', 'biography', 'image_url'])]
class Author extends Model
{
    /** @use HasFactory<AuthorFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsToMany<Literature, $this> */
    public function literatures(): BelongsToMany
    {
        return $this->belongsToMany(Literature::class)
            ->withPivot(['role', 'position'])
            ->withTimestamps();
    }

    /** @return HasMany<AuthorAlias, $this> */
    public function aliases(): HasMany
    {
        return $this->hasMany(AuthorAlias::class);
    }

    /** @return HasMany<CanonicalWork, $this> */
    public function canonicalWorks(): HasMany
    {
        return $this->hasMany(CanonicalWork::class, 'primary_author_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_favorite_authors')
            ->withPivot('position')
            ->withTimestamps();
    }
}
