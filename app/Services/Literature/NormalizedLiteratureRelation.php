<?php

namespace App\Services\Literature;

final readonly class NormalizedLiteratureRelation
{
    public function __construct(
        public string $type,
        public NormalizedLiterature $literature,
    ) {}
}
