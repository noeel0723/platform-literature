<?php

namespace App\Services\Literature;

use App\Models\CanonicalWork;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use Illuminate\Database\Eloquent\Builder;

final class CanonicalLiteratureSearch
{
    /** @return Builder<Literature> */
    public function query(string $query = '', string $selectedType = ''): Builder
    {
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
            ->with(['apiSource', 'authors', 'categories', 'sourceMapping'])
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
        $query->whereIn('type', Literature::supportedTypes())
            ->when($selectedType !== '', fn (Builder $literatures) => $literatures->where('type', $selectedType));
    }

    /** @param Builder<Literature> $builder */
    private function applySearch(Builder $builder, string $query): void
    {
        if ($query === '') {
            return;
        }

        $builder->where(function (Builder $search) use ($query): void {
            $search
                ->where('title', 'like', "%{$query}%")
                ->orWhere('original_title', 'like', "%{$query}%")
                ->orWhereHas('authors', function (Builder $authors) use ($query): void {
                    $authors
                        ->where('name', 'like', "%{$query}%")
                        ->orWhereHas('aliases', fn (Builder $aliases) => $aliases->where('name', 'like', "%{$query}%"));
                })
                ->orWhereHas('categories', fn (Builder $categories) => $categories->where('name', 'like', "%{$query}%"));
        });
    }
}
