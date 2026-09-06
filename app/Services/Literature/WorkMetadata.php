<?php

namespace App\Services\Literature;

final readonly class WorkMetadata
{
    public function __construct(
        public ?string $originalTitle = null,
        public ?string $tagline = null,
        public ?string $synopsis = null,
        public ?string $synopsisSourceName = null,
        public ?string $synopsisSourceUrl = null,
    ) {}
}
