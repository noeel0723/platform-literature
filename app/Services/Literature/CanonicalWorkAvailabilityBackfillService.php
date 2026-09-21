<?php

namespace App\Services\Literature;

use App\Models\CanonicalWork;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;

final class CanonicalWorkAvailabilityBackfillService
{
    public function __construct(
        private GoogleBooksAdapter $googleBooks,
        private AniListAdapter $aniList,
        private CanonicalWorkAvailabilityService $availability,
        private LiteratureIdentityNormalizer $identities,
        private AuthorNameNormalizer $authorNames,
    ) {}

    /** @return array{status: 'enriched'|'no_availability'|'ambiguous', links_synced: int} */
    public function enrich(CanonicalWork $canonicalWork): array
    {
        return match ($canonicalWork->type) {
            'novel' => $this->enrichNovel($canonicalWork),
            'manga', 'manhwa' => $this->enrichAniListWork($canonicalWork),
            default => $this->result('no_availability'),
        };
    }

    /** @return array{status: 'enriched'|'no_availability'|'ambiguous', links_synced: int} */
    private function enrichNovel(CanonicalWork $canonicalWork): array
    {
        foreach ($this->isbnIdentifiers($canonicalWork) as $isbn) {
            $candidates = $this->googleBooks->findByIsbn($isbn);
            $candidate = $candidates->first(
                fn (NormalizedLiterature $item): bool => $this->normalizeIsbn($item->identifier) === $isbn,
            );

            if (! $candidate instanceof NormalizedLiterature && $candidates->count() === 1) {
                $soleCandidate = $candidates->first();
                $candidate = $soleCandidate?->identifier === null ? $soleCandidate : null;
            }

            if ($candidate instanceof NormalizedLiterature && $candidate->links !== []) {
                return $this->sync($canonicalWork, $candidate);
            }
        }

        $googleMapping = $this->sourceMapping($canonicalWork, ['google-books', 'google_books']);

        if ($googleMapping !== null) {
            $candidate = $this->googleBooks->findById($googleMapping->source_external_id);

            if ($candidate instanceof NormalizedLiterature && $candidate->links !== []) {
                return $this->sync($canonicalWork, $candidate);
            }
        }

        $author = $this->primaryAuthorName($canonicalWork);

        if ($author === null) {
            return $this->result('no_availability');
        }

        $matches = $this->googleBooks
            ->findByTitleAndAuthor($canonicalWork->canonical_title, $author)
            ->filter(fn (NormalizedLiterature $candidate): bool => $this->matches($canonicalWork, $candidate, true))
            ->values();

        if ($matches->count() > 1) {
            return $this->result('ambiguous');
        }

        $candidate = $matches->first();

        return $candidate instanceof NormalizedLiterature && $candidate->links !== []
            ? $this->sync($canonicalWork, $candidate)
            : $this->result('no_availability');
    }

    /** @return array{status: 'enriched'|'no_availability'|'ambiguous', links_synced: int} */
    private function enrichAniListWork(CanonicalWork $canonicalWork): array
    {
        $aniListMapping = $this->sourceMapping($canonicalWork, ['anilist']);

        if ($aniListMapping !== null) {
            $candidate = $this->aniList->findById($aniListMapping->source_external_id);

            return $candidate instanceof NormalizedLiterature && $candidate->links !== []
                ? $this->sync($canonicalWork, $candidate)
                : $this->result('no_availability');
        }

        $matches = $this->aniList
            ->search($canonicalWork->canonical_title, $canonicalWork->type, 5)
            ->filter(fn (NormalizedLiterature $candidate): bool => $this->matches($canonicalWork, $candidate, false))
            ->values();

        if ($matches->count() > 1) {
            return $this->result('ambiguous');
        }

        $candidate = $matches->first();

        return $candidate instanceof NormalizedLiterature && $candidate->links !== []
            ? $this->sync($canonicalWork, $candidate)
            : $this->result('no_availability');
    }

    /** @return list<string> */
    private function isbnIdentifiers(CanonicalWork $canonicalWork): array
    {
        $identifiers = $canonicalWork->identifiers
            ->where('scheme', 'isbn')
            ->pluck('value');

        foreach ($canonicalWork->sourceMappings as $mapping) {
            $literature = $mapping->literature;

            if (! $literature instanceof Literature) {
                continue;
            }

            foreach ($this->identities->identifiers($literature) as $identifier) {
                if ($identifier['scheme'] === 'isbn') {
                    $identifiers->push($identifier['value']);
                }
            }
        }

        return $identifiers
            ->map(fn (string $isbn): string => $this->normalizeIsbn($isbn))
            ->filter(fn (string $isbn): bool => $this->isValidIsbn($isbn))
            ->unique()
            ->sortByDesc(fn (string $isbn): int => strlen($isbn))
            ->values()
            ->all();
    }

    /** @param list<string> $sourceKeys */
    private function sourceMapping(CanonicalWork $canonicalWork, array $sourceKeys): ?LiteratureSourceMapping
    {
        return $canonicalWork->sourceMappings->first(
            fn (LiteratureSourceMapping $mapping): bool => in_array($mapping->apiSource?->key, $sourceKeys, true)
                && filled($mapping->source_external_id),
        );
    }

    private function primaryAuthorName(CanonicalWork $canonicalWork): ?string
    {
        if (filled($canonicalWork->primaryAuthor?->name)) {
            return $canonicalWork->primaryAuthor->name;
        }

        foreach ($canonicalWork->sourceMappings as $mapping) {
            $name = $mapping->literature?->authors?->first()?->name;

            if (filled($name)) {
                return $name;
            }
        }

        return null;
    }

    private function matches(
        CanonicalWork $canonicalWork,
        NormalizedLiterature $candidate,
        bool $requireAuthor,
    ): bool {
        $expectedTitle = $canonicalWork->normalized_title
            ?: $this->identities->normalizeTitle($canonicalWork->canonical_title);
        $candidateTitles = collect([$candidate->title, $candidate->originalTitle])
            ->filter()
            ->map(fn (string $title): string => $this->identities->normalizeTitle($title));

        if (! $candidateTitles->contains($expectedTitle)) {
            return false;
        }

        if (
            $canonicalWork->publication_year !== null
            && $candidate->publicationYear !== null
            && $canonicalWork->publication_year !== $candidate->publicationYear
        ) {
            return false;
        }

        $author = $this->primaryAuthorName($canonicalWork);

        if ($author === null) {
            return ! $requireAuthor;
        }

        $expectedAuthor = $this->authorNames->normalize($author);

        return collect($candidate->authors)
            ->map(fn (string $name): string => $this->authorNames->normalize($name))
            ->contains($expectedAuthor);
    }

    /** @return array{status: 'enriched', links_synced: int} */
    private function sync(CanonicalWork $canonicalWork, NormalizedLiterature $candidate): array
    {
        return [
            'status' => 'enriched',
            'links_synced' => $this->availability->sync($canonicalWork, $candidate->links),
        ];
    }

    /** @return array{status: 'no_availability'|'ambiguous', links_synced: 0} */
    private function result(string $status): array
    {
        return ['status' => $status, 'links_synced' => 0];
    }

    private function normalizeIsbn(?string $isbn): string
    {
        return strtoupper(preg_replace('/[^0-9X]/i', '', (string) $isbn) ?? '');
    }

    private function isValidIsbn(string $isbn): bool
    {
        if (preg_match('/^\d{13}$/', $isbn) === 1) {
            $sum = 0;

            foreach (str_split($isbn) as $position => $digit) {
                $sum += (int) $digit * ($position % 2 === 0 ? 1 : 3);
            }

            return $sum % 10 === 0;
        }

        if (preg_match('/^\d{9}[\dX]$/', $isbn) !== 1) {
            return false;
        }

        $sum = 0;

        foreach (str_split($isbn) as $position => $digit) {
            $sum += ($digit === 'X' ? 10 : (int) $digit) * (10 - $position);
        }

        return $sum % 11 === 0;
    }
}
