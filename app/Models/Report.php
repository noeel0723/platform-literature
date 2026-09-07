<?php

namespace App\Models;

use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'reporter_id',
    'reportable_type',
    'reportable_id',
    'reason',
    'details',
    'status',
    'resolved_by',
    'resolved_at',
    'resolution_note',
])]
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    /** @var array<string, string> */
    public const REASON_LABELS = [
        'harassment' => 'Harassment or bullying',
        'hate' => 'Hate speech',
        'spam' => 'Spam or misleading content',
        'spoiler' => 'Unmarked spoiler',
        'inappropriate' => 'Inappropriate content',
        'other' => 'Other concern',
    ];

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        'pending' => 'Pending',
        'resolved' => 'Resolved',
        'dismissed' => 'Dismissed',
    ];

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return MorphTo<Model, $this> */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }
}
