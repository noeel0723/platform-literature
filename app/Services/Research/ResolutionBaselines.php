<?php

namespace App\Services\Research;

use App\Services\Literature\LiteratureIdentityNormalizer;

final class ResolutionBaselines
{
    public function __construct(private LiteratureIdentityNormalizer $normalizer) {}

    /** Uses only the stored post-ingestion title and frozen primary author identity, never work mappings. */
    public function predict(array $a, array $b): array
    {
        $title = $this->normalizer->normalizeTitle($a['attributes']['title']);
        $sameTitle = $title !== '' && $title === $this->normalizer->normalizeTitle($b['attributes']['title']);
        $authorA = $a['authors'][0]['id'] ?? null;
        $authorB = $b['authors'][0]['id'] ?? null;

        return [
            'exact_title' => $sameTitle,
            'exact_title_author' => $sameTitle && $authorA !== null && $authorA === $authorB,
        ];
    }
}
