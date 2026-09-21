<?php

namespace App\Services\Literature;

final readonly class NormalizedLiteratureLink
{
    public function __construct(
        public string $provider,
        public string $url,
        public string $type,
        public string $source,
        public ?string $region = null,
        public ?string $language = null,
        public bool $isOfficial = true,
    ) {}
}
