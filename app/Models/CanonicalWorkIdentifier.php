<?php

namespace App\Models;

use Database\Factories\CanonicalWorkIdentifierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['canonical_work_id', 'scheme', 'value', 'source_key'])]
class CanonicalWorkIdentifier extends Model
{
    /** @use HasFactory<CanonicalWorkIdentifierFactory> */
    use HasFactory;

    /** @return BelongsTo<CanonicalWork, $this> */
    public function canonicalWork(): BelongsTo
    {
        return $this->belongsTo(CanonicalWork::class);
    }
}
