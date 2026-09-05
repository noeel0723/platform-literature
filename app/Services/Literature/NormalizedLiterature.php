<?php

namespace App\Services\Literature;

final readonly class NormalizedLiterature
{
    /**
     * @param  list<string>  $authors
     * @param  list<string>  $categories
     */
    public function __construct(
        public string $externalId,
        public string $title,
        public string $type,
        public array $authors,
        public array $categories,
        public ?int $publicationYear,
        public ?string $tagline,
        public ?string $synopsis,
        public ?string $publisher,
        public ?string $language,
        public ?string $format,
        public ?string $identifier,
        public ?string $coverUrl,
    ) {}
}
