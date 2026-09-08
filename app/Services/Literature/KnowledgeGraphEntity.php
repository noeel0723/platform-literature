<?php

namespace App\Services\Literature;

final readonly class KnowledgeGraphEntity
{
    /** @param list<string> $types */
    public function __construct(
        public string $id,
        public string $name,
        public array $types,
        public ?string $description,
        public ?string $detailedDescription,
        public ?string $sourceUrl,
        public ?string $officialUrl,
        public float $score,
        public ?string $imageUrl = null,
        public ?string $imageLicenseUrl = null,
    ) {}
}
