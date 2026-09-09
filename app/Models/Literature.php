<?php

namespace App\Models;

use Database\Factories\LiteratureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'api_source_id',
    'external_id',
    'knowledge_graph_id',
    'knowledge_graph_types',
    'knowledge_graph_url',
    'knowledge_graph_score',
    'slug',
    'title',
    'original_title',
    'type',
    'publication_year',
    'tagline',
    'synopsis',
    'synopsis_source_name',
    'synopsis_source_url',
    'publisher',
    'language',
    'format',
    'identifier',
    'cover_url',
    'theme',
])]
class Literature extends Model
{
    /** @use HasFactory<LiteratureFactory> */
    use HasFactory;

    /** @var array<string, string> */
    public const TYPE_LABELS = [
        'novel' => 'Novel',
        'western-comic' => 'Comic',
        'manga' => 'Manga',
        'manhwa' => 'Manhwa',
    ];

    /** @return list<string> */
    public static function supportedTypes(): array
    {
        return array_keys(self::TYPE_LABELS);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? Str::headline($this->type);
    }

    public function displayTitle(): string
    {
        if (in_array($this->type, ['manga', 'manhwa'], true)) {
            return $this->title;
        }

        return $this->original_title ?? $this->title;
    }

    public function alternateTitle(): ?string
    {
        $alternateTitle = in_array($this->type, ['manga', 'manhwa'], true)
            ? $this->original_title
            : ($this->original_title === null ? null : $this->title);

        return filled($alternateTitle) && $alternateTitle !== $this->displayTitle()
            ? $alternateTitle
            : null;
    }

    /** @return BelongsTo<ApiSource, $this> */
    public function apiSource(): BelongsTo
    {
        return $this->belongsTo(ApiSource::class);
    }

    /** @return BelongsToMany<Author, $this> */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class)
            ->withPivot(['role', 'position'])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }

    /** @return HasMany<ReadingList, $this> */
    public function readingLists(): HasMany
    {
        return $this->hasMany(ReadingList::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /** @return HasMany<Discussion, $this> */
    public function discussions(): HasMany
    {
        return $this->hasMany(Discussion::class);
    }

    /** @return HasMany<LiteratureRelation, $this> */
    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(LiteratureRelation::class);
    }

    /** @return HasMany<LiteratureRelation, $this> */
    public function incomingRelations(): HasMany
    {
        return $this->hasMany(LiteratureRelation::class, 'related_literature_id');
    }

    /** @return HasOne<LiteratureSourceMapping, $this> */
    public function sourceMapping(): HasOne
    {
        return $this->hasOne(LiteratureSourceMapping::class);
    }

    /** @param Builder<Literature> $query */
    public function scopeCanonicalRepresentatives(Builder $query): void
    {
        $query->where(function (Builder $representatives): void {
            $representatives
                ->whereIn('literatures.id', CanonicalWork::query()
                    ->select('preferred_literature_id')
                    ->whereNotNull('preferred_literature_id'))
                ->orWhereDoesntHave('sourceMapping');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'knowledge_graph_types' => 'array',
            'knowledge_graph_score' => 'float',
            'publication_year' => 'integer',
        ];
    }
}
