<?php

namespace App\Models;

use Database\Factories\ApiSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'name', 'base_url', 'supported_types', 'is_active'])]
class ApiSource extends Model
{
    /** @use HasFactory<ApiSourceFactory> */
    use HasFactory;

    /** @return HasMany<Literature, $this> */
    public function literatures(): HasMany
    {
        return $this->hasMany(Literature::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'supported_types' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
