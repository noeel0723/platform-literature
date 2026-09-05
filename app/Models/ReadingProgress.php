<?php

namespace App\Models;

use Database\Factories\ReadingProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reading_list_id', 'current_value', 'total_value', 'unit'])]
class ReadingProgress extends Model
{
    /** @use HasFactory<ReadingProgressFactory> */
    use HasFactory;

    /** @return BelongsTo<ReadingList, $this> */
    public function readingList(): BelongsTo
    {
        return $this->belongsTo(ReadingList::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'current_value' => 'integer',
            'total_value' => 'integer',
        ];
    }
}
