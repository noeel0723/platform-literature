<?php

namespace App\Models;

use Database\Factories\LiteratureMetadataOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'literature_id',
    'canonical_work_id',
    'edited_by',
    'title',
    'original_title',
    'publication_year',
    'tagline',
    'synopsis',
    'cover_path',
    'cover_url',
    'backdrop_path',
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

    public function uploadedCoverUrl(): ?string
    {
        return filled($this->cover_path)
            ? Storage::disk('public')->url($this->cover_path)
            : null;
    }

    public function uploadedBackdropUrl(): ?string
    {
        return filled($this->backdrop_path)
            ? Storage::disk('public')->url($this->backdrop_path)
            : null;
    }

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
