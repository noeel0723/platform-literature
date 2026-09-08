<?php

namespace App\Models;

use Database\Factories\AuthorAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['author_id', 'name', 'normalized_name', 'source', 'external_id', 'source_url'])]
class AuthorAlias extends Model
{
    /** @use HasFactory<AuthorAliasFactory> */
    use HasFactory;

    /** @return BelongsTo<Author, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }
}
