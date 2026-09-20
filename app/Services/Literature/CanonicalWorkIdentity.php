<?php

namespace App\Services\Literature;

use App\Models\CanonicalWork;
use App\Models\Literature;
use Illuminate\Support\Collection;

final class CanonicalWorkIdentity
{
    public function __construct(private readonly SemanticLiteratureResolver $resolver) {}

    public function key(Literature $literature): string
    {
        $literature->loadMissing('sourceMapping');

        return $literature->sourceMapping === null
            ? 'literature:'.$literature->getKey()
            : 'canonical:'.$literature->sourceMapping->canonical_work_id;
    }

    public function representative(Literature $literature): Literature
    {
        $literature->loadMissing('sourceMapping.canonicalWork.preferredLiterature');

        return $literature->sourceMapping?->canonicalWork?->preferredLiterature ?? $literature;
    }

    /** @return Collection<int, int> */
    public function equivalentLiteratureIds(Literature $literature): Collection
    {
        $literature->loadMissing('sourceMapping.canonicalWork.sourceMappings');
        $canonicalWork = $literature->sourceMapping?->canonicalWork;

        if ($canonicalWork === null) {
            return collect([(int) $literature->getKey()]);
        }

        return $canonicalWork->sourceMappings
            ->pluck('literature_id')
            ->push((int) $literature->getKey())
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    public function canonicalWork(Literature $literature): CanonicalWork
    {
        $literature->loadMissing('sourceMapping.canonicalWork');
        $mapping = $literature->sourceMapping ?? $this->resolver->resolve($literature);

        return $mapping->canonicalWork()->firstOrFail();
    }
}
