<?php

namespace App\Services\Literature;

use App\Models\CanonicalWork;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class CanonicalLiteratureSearch
{
    public function __construct(private CatalogResultRanker $ranker) {}

    /**
     * Rank every matching source before selecting one row per canonical work and paginating.
     *
     * @return Builder<Literature>
     */
    public function rankedQuery(string $query = '', string $selectedType = ''): Builder
    {
        $query = Str::squish($query);

        if ($query === '') {
            return $this->query('', $selectedType)->latest('literatures.updated_at')->latest('literatures.id');
        }

        $matches = Literature::query();
        $this->applySupportedType($matches, $selectedType);

        return $this->ranker->rankQuery($matches, $query)
            ->when($selectedType !== '', fn (Builder $literatures) => $literatures->where('literatures.type', $selectedType))
            ->with(['apiSource', 'authors', 'categories', 'sourceMapping.canonicalWork.metadataOverride', 'metadataOverride']);
    }

    /** @return Builder<Literature> */
    public function query(string $query = '', string $selectedType = ''): Builder
    {
        return $this->representativeQuery($query, $selectedType)
            ->with(['apiSource', 'authors', 'categories', 'sourceMapping.canonicalWork.metadataOverride', 'metadataOverride']);
    }

    /**
     * Build the canonical representative query without eager-loading display data.
     *
     * @return Builder<Literature>
     */
    public function representativeQuery(string $query = '', string $selectedType = ''): Builder
    {
        if ($query === '') {
            return Literature::query()
                ->whereIn('literatures.type', Literature::supportedTypes())
                ->when($selectedType !== '', fn (Builder $literatures) => $literatures->where('literatures.type', $selectedType))
                ->canonicalRepresentatives();
        }

        $matchingCanonicalIds = LiteratureSourceMapping::query()
            ->select('canonical_work_id')
            ->whereHas('literature', function (Builder $literatures) use ($query, $selectedType): void {
                $this->applySupportedType($literatures, $selectedType);
                $this->applySearch($literatures, $query);
            });
        $preferredLiteratureIds = CanonicalWork::query()
            ->select('preferred_literature_id')
            ->whereIn('id', $matchingCanonicalIds)
            ->whereNotNull('preferred_literature_id');

        return Literature::query()
            ->whereIn('type', Literature::supportedTypes())
            ->when($selectedType !== '', fn (Builder $literatures) => $literatures->where('type', $selectedType))
            ->where(function (Builder $literatures) use ($preferredLiteratureIds, $query): void {
                $literatures
                    ->whereIn('literatures.id', $preferredLiteratureIds)
                    ->orWhere(function (Builder $legacy) use ($query): void {
                        $legacy->whereDoesntHave('sourceMapping');
                        $this->applySearch($legacy, $query);
                    });
            });
    }

    /** @param Builder<Literature> $query */
    private function applySupportedType(Builder $query, string $selectedType): void
    {
        $query->whereIn('literatures.type', Literature::supportedTypes())
            ->when($selectedType !== '', fn (Builder $literatures) => $literatures->where('literatures.type', $selectedType));
    }

    /** @param Builder<Literature> $builder */
    private function applySearch(Builder $builder, string $query): void
    {
        if ($query === '') {
            return;
        }

        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($query)).'%';

        $builder->where(function (Builder $search) use ($pattern): void {
            $search
                ->whereRaw("LOWER(literatures.title) LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("LOWER(literatures.original_title) LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('metadataOverride', fn (Builder $overrides) => $overrides
                    ->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(original_title) LIKE ? ESCAPE '!'", [$pattern]))
                ->orWhereHas('sourceMapping.canonicalWork', fn (Builder $works) => $works
                    ->whereRaw("LOWER(canonical_title) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('metadataOverride', fn (Builder $overrides) => $overrides
                        ->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("LOWER(original_title) LIKE ? ESCAPE '!'", [$pattern])))
                ->orWhereHas('authors', function (Builder $authors) use ($pattern): void {
                    $authors
                        ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereHas('aliases', fn (Builder $aliases) => $aliases->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern]));
                })
                ->orWhereHas('categories', fn (Builder $categories) => $categories->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern]));
        });
    }
}
