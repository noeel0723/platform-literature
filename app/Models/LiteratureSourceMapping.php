<?php

namespace App\Models;

use Database\Factories\LiteratureSourceMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'canonical_work_id',
    'literature_id',
    'api_source_id',
    'source_external_id',
    'match_method',
    'mapping_status',
    'confidence',
    'candidate_work_ids',
    'field_provenance',
])]
class LiteratureSourceMapping extends Model
{
    /** @use HasFactory<LiteratureSourceMappingFactory> */
    use HasFactory;

    public const STATUS_MATCHED = 'matched';

    public const STATUS_NEW = 'new';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    /** @return BelongsTo<CanonicalWork, $this> */
    public function canonicalWork(): BelongsTo
    {
        return $this->belongsTo(CanonicalWork::class);
    }

    /** @return BelongsTo<Literature, $this> */
    public function literature(): BelongsTo
    {
        return $this->belongsTo(Literature::class);
    }

    /** @return BelongsTo<ApiSource, $this> */
    public function apiSource(): BelongsTo
    {
        return $this->belongsTo(ApiSource::class);
    }

    /** @param Builder<LiteratureSourceMapping> $query */
    public function scopeNeedsReview(Builder $query): void
    {
        $query->where('mapping_status', self::STATUS_NEEDS_REVIEW);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'candidate_work_ids' => 'array',
            'field_provenance' => 'array',
            'confidence' => 'float',
        ];
    }
}
