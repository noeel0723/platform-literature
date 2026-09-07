<?php

namespace App\Models;

use Database\Factories\LiteratureRelationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['literature_id', 'related_literature_id', 'relation_type', 'source'])]
class LiteratureRelation extends Model
{
    /** @use HasFactory<LiteratureRelationFactory> */
    use HasFactory;

    /** @var array<string, string> */
    public const TYPE_LABELS = [
        'sequel' => 'Sequel',
        'prequel' => 'Prequel',
        'spin_off' => 'Spin-off',
        'adaptation' => 'Adaptation',
        'source' => 'Source',
        'side_story' => 'Side Story',
        'alternative_version' => 'Alternative Version',
        'same_series' => 'Same Series',
        'related' => 'Related',
    ];

    /** @return BelongsTo<Literature, $this> */
    public function literature(): BelongsTo
    {
        return $this->belongsTo(Literature::class);
    }

    /** @return BelongsTo<Literature, $this> */
    public function relatedLiterature(): BelongsTo
    {
        return $this->belongsTo(Literature::class, 'related_literature_id');
    }

    public static function inverseType(string $relationType): string
    {
        return match ($relationType) {
            'sequel' => 'prequel',
            'prequel' => 'sequel',
            'adaptation' => 'source',
            'source' => 'adaptation',
            'alternative_version' => 'alternative_version',
            'same_series' => 'same_series',
            default => 'related',
        };
    }
}
