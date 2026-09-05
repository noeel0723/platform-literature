<?php

namespace App\Models;

use Database\Factories\AuthorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'biography', 'image_url'])]
class Author extends Model
{
    /** @use HasFactory<AuthorFactory> */
    use HasFactory;

    /** @return BelongsToMany<Literature, $this> */
    public function literatures(): BelongsToMany
    {
        return $this->belongsToMany(Literature::class)
            ->withPivot(['role', 'position'])
            ->withTimestamps();
    }
}
