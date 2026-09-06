<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'location', 'bio', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @return HasMany<ReadingList, $this> */
    public function readingLists(): HasMany
    {
        return $this->hasMany(ReadingList::class);
    }

    /** @return HasManyThrough<ReadingLog, ReadingList, $this> */
    public function readingLogs(): HasManyThrough
    {
        return $this->hasManyThrough(ReadingLog::class, ReadingList::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<Discussion, $this> */
    public function discussions(): HasMany
    {
        return $this->hasMany(Discussion::class);
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return HasMany<Like, $this> */
    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    /** @return BelongsToMany<Literature, $this> */
    public function favoriteLiteratures(): BelongsToMany
    {
        return $this->belongsToMany(Literature::class, 'user_favorite_literatures')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /** @return BelongsToMany<Author, $this> */
    public function favoriteAuthors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'user_favorite_authors')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function getRouteKeyName(): string
    {
        return 'username';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
