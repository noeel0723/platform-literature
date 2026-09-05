<?php

namespace App\Models;

use Database\Factories\ReadingLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reading_list_id',
    'event_type',
    'status',
    'progress_value',
    'progress_total',
    'progress_unit',
    'note',
    'occurred_at',
])]
class ReadingLog extends Model
{
    /** @use HasFactory<ReadingLogFactory> */
    use HasFactory;

    /** @var array<string, string> */
    public const EVENT_LABELS = [
        'added_to_readlist' => 'Ditambahkan ke Readlist',
        'started' => 'Mulai membaca',
        'progress_updated' => 'Memperbarui progres',
        'completed' => 'Menyelesaikan bacaan',
        'dnf' => 'Berhenti membaca',
        'reread' => 'Mulai membaca ulang',
        'note_added' => 'Menambahkan catatan',
        'status_updated' => 'Mengubah status bacaan',
    ];

    /** @return BelongsTo<ReadingList, $this> */
    public function readingList(): BelongsTo
    {
        return $this->belongsTo(ReadingList::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'progress_value' => 'integer',
            'progress_total' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
