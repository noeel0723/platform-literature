<?php

namespace App\Services\Literature;

use App\Models\CanonicalWork;
use App\Models\Literature;

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

    public function canonicalWork(Literature $literature): CanonicalWork
    {
        $literature->loadMissing('sourceMapping.canonicalWork');
        $mapping = $literature->sourceMapping ?? $this->resolver->resolve($literature);

        return $mapping->canonicalWork()->firstOrFail();
    }
}
