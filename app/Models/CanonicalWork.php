<?php

namespace App\Models;

use Database\Factories\CanonicalWorkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['primary_author_id', 'preferred_literature_id', 'canonical_title', 'normalized_title', 'type', 'publication_year'])]
class CanonicalWork extends Model
{
    /** @use HasFactory<CanonicalWorkFactory> */
    use HasFactory;

    /** @return BelongsTo<Author, $this> */
    public function primaryAuthor(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'primary_author_id');
    }

    /** @return BelongsTo<Literature, $this> */
    public function preferredLiterature(): BelongsTo
    {
        return $this->belongsTo(Literature::class, 'preferred_literature_id');
    }

    /** @return HasMany<CanonicalWorkIdentifier, $this> */
    public function identifiers(): HasMany
    {
        return $this->hasMany(CanonicalWorkIdentifier::class);
    }

    /** @return HasMany<LiteratureSourceMapping, $this> */
    public function sourceMappings(): HasMany
    {
        return $this->hasMany(LiteratureSourceMapping::class);
    }

    /** @return HasOne<LiteratureMetadataOverride, $this> */
    public function metadataOverride(): HasOne
    {
        return $this->hasOne(LiteratureMetadataOverride::class);
    }

    /** @return BelongsToMany<Literature, $this> */
    public function literatures(): BelongsToMany
    {
        return $this->belongsToMany(Literature::class, 'literature_source_mappings')
            ->withPivot(['api_source_id', 'match_method', 'mapping_status', 'confidence'])
            ->withTimestamps();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'publication_year' => 'integer',
        ];
    }
}
