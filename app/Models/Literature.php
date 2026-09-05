<?php

namespace App\Models;

use Database\Factories\LiteratureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'api_source_id',
    'external_id',
    'slug',
    'title',
    'original_title',
    'type',
    'publication_year',
    'tagline',
    'synopsis',
    'publisher',
    'language',
    'format',
    'identifier',
    'cover_url',
    'theme',
])]
class Literature extends Model
{
    /** @use HasFactory<LiteratureFactory> */
    use HasFactory;

    /** @return BelongsTo<ApiSource, $this> */
    public function apiSource(): BelongsTo
    {
        return $this->belongsTo(ApiSource::class);
    }

    /** @return BelongsToMany<Author, $this> */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class)
            ->withPivot(['role', 'position'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'publication_year' => 'integer',
        ];
    }
}
