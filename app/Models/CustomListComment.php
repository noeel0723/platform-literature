<?php

namespace App\Models;

use Database\Factories\CustomListCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['custom_list_id', 'user_id', 'body'])]
class CustomListComment extends Model
{
    /** @use HasFactory<CustomListCommentFactory> */
    use HasFactory;

    /** @return BelongsTo<CustomList, $this> */
    public function customList(): BelongsTo
    {
        return $this->belongsTo(CustomList::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
