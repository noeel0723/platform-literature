<?php

namespace App\Models;

use Database\Factories\CustomListItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['custom_list_id', 'canonical_work_id', 'literature_id', 'position'])]
class CustomListItem extends Model
{
    /** @use HasFactory<CustomListItemFactory> */
    use HasFactory;

    /** @return BelongsTo<CustomList, $this> */
    public function customList(): BelongsTo
    {
        return $this->belongsTo(CustomList::class);
    }

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

    public function displayLiterature(): ?Literature
    {
        return $this->literature ?? $this->canonicalWork?->preferredLiterature;
    }
}
