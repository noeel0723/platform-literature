<?php

namespace App\Models;

use Database\Factories\LiteratureMetadataOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'literature_id',
    'canonical_work_id',
    'edited_by',
    'title',
    'original_title',
    'publication_year',
    'tagline',
    'synopsis',
    'cover_url',
    'backdrop_url',
    'publisher',
    'language',
    'format',
    'source_url',
    'notes',
])]
class LiteratureMetadataOverride extends Model
{
    /** @use HasFactory<LiteratureMetadataOverrideFactory> */
    use HasFactory;

    /** @return BelongsTo<Literature, $this> */
    public function literature(): BelongsTo
    {
        return $this->belongsTo(Literature::class);
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    /** @return BelongsTo<CanonicalWork, $this> */
    public function canonicalWork(): BelongsTo
    {
        return $this->belongsTo(CanonicalWork::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['publication_year' => 'integer'];
    }
}
