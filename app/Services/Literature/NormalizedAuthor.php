<?php

namespace App\Services\Literature;

final readonly class NormalizedAuthor
{
    public function __construct(
        public string $name,
        public ?string $imageUrl = null,
        public ?string $biography = null,
        public ?string $sourceUrl = null,
        public ?string $externalId = null,
        public ?string $externalEntityId = null,
    ) {}
}
