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
    'backdrop_url',
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
        $override = $this->effectiveMetadataOverride();

        if (filled($override?->title)) {
            return $override->title;
        }

        if (in_array($this->type, ['manga', 'manhwa'], true)) {
            return $this->title;
        }

        return $override?->original_title ?? $this->original_title ?? $this->title;
    }

    public function alternateTitle(): ?string
    {
        $override = $this->effectiveMetadataOverride();
        $title = $override?->title ?? $this->title;
        $originalTitle = $override?->original_title ?? $this->original_title;
        $alternateTitle = in_array($this->type, ['manga', 'manhwa'], true)
            ? $originalTitle
            : ($originalTitle === null ? null : $title);

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

    /** @return HasOne<LiteratureMetadataOverride, $this> */
    public function metadataOverride(): HasOne
    {
        return $this->hasOne(LiteratureMetadataOverride::class);
    }

    public function displayPublicationYear(): ?int
    {
        return $this->effectiveMetadataOverride()?->publication_year ?? $this->publication_year;
    }

    public function displayTagline(): ?string
    {
        return $this->effectiveMetadataOverride()?->tagline ?? $this->tagline;
    }

    public function displaySynopsis(): ?string
    {
        return $this->effectiveMetadataOverride()?->synopsis ?? $this->synopsis;
    }

    public function displayCoverUrl(): ?string
    {
        return $this->effectiveMetadataOverride()?->cover_url ?? $this->cover_url;
    }

    public function displayBackdropUrl(): ?string
    {
        return $this->effectiveMetadataOverride()?->backdrop_url ?? $this->backdrop_url;
    }

    public function displayPublisher(): ?string
    {
        return $this->effectiveMetadataOverride()?->publisher ?? $this->publisher;
    }

    public function displayLanguage(): ?string
    {
        return $this->effectiveMetadataOverride()?->language ?? $this->language;
    }

    public function displayFormat(): ?string
    {
        return $this->effectiveMetadataOverride()?->format ?? $this->format;
    }

    public function hasCuratedMetadata(): bool
    {
        return $this->effectiveMetadataOverride() !== null;
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

    public function effectiveMetadataOverride(): ?LiteratureMetadataOverride
    {
        if (! $this->exists && ! $this->relationLoaded('metadataOverride')) {
            return null;
        }

        $sourceMapping = $this->getRelationValue('sourceMapping');
        $canonicalOverride = $sourceMapping?->canonicalWork?->metadataOverride;

        if ($canonicalOverride instanceof LiteratureMetadataOverride) {
            return $canonicalOverride;
        }

        $override = $this->getRelationValue('metadataOverride');

        return $override instanceof LiteratureMetadataOverride ? $override : null;
    }
}
