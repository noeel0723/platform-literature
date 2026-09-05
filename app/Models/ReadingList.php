<?php

namespace App\Models;

use Database\Factories\ReadingListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'literature_id',
    'status',
    'started_at',
    'completed_at',
    'reread_count',
])]
class ReadingList extends Model
{
    /** @use HasFactory<ReadingListFactory> */
    use HasFactory;

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        'want_to_read' => 'Ingin dibaca',
        'reading' => 'Sedang dibaca',
        'completed' => 'Selesai',
        'dnf' => 'DNF / Berhenti',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Literature, $this> */
    public function literature(): BelongsTo
    {
        return $this->belongsTo(Literature::class);
    }

    /** @return HasOne<ReadingProgress, $this> */
    public function progress(): HasOne
    {
        return $this->hasOne(ReadingProgress::class);
    }

    /** @return HasMany<ReadingLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(ReadingLog::class)->latest('occurred_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'reread_count' => 'integer',
        ];
    }
}
