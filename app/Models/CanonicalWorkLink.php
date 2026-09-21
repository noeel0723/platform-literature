<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'canonical_work_id',
    'provider',
    'url',
    'link_type',
    'region',
    'language',
    'source',
    'is_official',
    'is_active',
    'verified_at',
])]
class CanonicalWorkLink extends Model
{
    /** @var array<string, string> */
    public const TYPE_LABELS = [
        'read' => 'Read',
        'preview' => 'Preview',
        'buy' => 'Buy',
        'borrow' => 'Borrow',
    ];

    /** @return BelongsTo<CanonicalWork, $this> */
    public function canonicalWork(): BelongsTo
    {
        return $this->belongsTo(CanonicalWork::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_official' => 'boolean',
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }
}
