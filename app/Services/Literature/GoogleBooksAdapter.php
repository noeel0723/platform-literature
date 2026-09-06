<?php

namespace App\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class GoogleBooksAdapter
{
    public function __construct(private WorkMetadataEnricher $metadataEnricher) {}

    /** @return Collection<int, NormalizedLiterature> */
    public function search(string $query, int $limit = 6, string $requestedType = 'all'): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        if (! in_array($requestedType, ['all', 'book', 'novel'], true)) {
            throw new InvalidArgumentException('Google Books only supports all, book, and novel catalog types.');
        }

        $apiKey = trim((string) config('services.google_books.key'));

        if ($apiKey === '') {
            throw new LiteratureSourceUnavailable(
                'Google Books',
                'Google Books API key is not configured.',
            );
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('services.google_books.base_url'), '/'))
                ->acceptJson()
                ->connectTimeout((int) config('services.google_books.connect_timeout', 3))
                ->timeout((int) config('services.google_books.timeout', 8))
                ->get('/volumes', [
                    'q' => $requestedType === 'novel' ? "{$query} subject:fiction" : $query,
                    'maxResults' => max(1, min($limit, 40)),
                    'printType' => 'books',
                    'projection' => 'full',
                    'key' => $apiKey,
                ]);
        } catch (ConnectionException $exception) {
            throw new LiteratureSourceUnavailable(
                'Google Books',
                'Google Books could not be reached.',
                $exception,
            );
        }

        if ($response->status() === 429) {
            throw new LiteratureSourceUnavailable(
                'Google Books',
                'Google Books rate limit was reached.',
            );
        }

        if ($response->failed()) {
            throw new LiteratureSourceUnavailable(
                'Google Books',
                "Google Books returned HTTP {$response->status()}.",
            );
        }

        $items = $response->json('items');

        if (! is_array($items)) {
            return collect();
        }

        return collect($items)
            ->map(fn (mixed $item): ?NormalizedLiterature => $this->normalize($item, $requestedType))
            ->filter()
            ->values();
    }

    private function normalize(mixed $item, string $requestedType = 'all'): ?NormalizedLiterature
    {
        if (! is_array($item)) {
            return null;
        }

        $externalId = trim((string) Arr::get($item, 'id', ''));
        $title = $this->cleanText(Arr::get($item, 'volumeInfo.title'));

        if ($externalId === '' || $title === null) {
            return null;
        }

        $publishedDate = $this->cleanText(Arr::get($item, 'volumeInfo.publishedDate'));
        $publicationYear = null;

        if ($publishedDate !== null && preg_match('/^\d{4}/', $publishedDate, $matches) === 1) {
            $publicationYear = (int) $matches[0];
        }

        $categories = $this->stringList(Arr::get($item, 'volumeInfo.categories'));
        $authors = $this->stringList(Arr::get($item, 'volumeInfo.authors'));
        $language = $this->cleanText(Arr::get($item, 'volumeInfo.language'));
        $sourceTagline = $this->cleanText(Arr::get($item, 'volumeInfo.subtitle'));
        $sourceSynopsis = $this->cleanText(Arr::get($item, 'volumeInfo.description'));
        $contentLanguage = (string) config('services.work_metadata.content_language', 'en');
        $sourceUsesContentLanguage = $language === null || $language === $contentLanguage;
        $enrichment = $sourceSynopsis === null || ! $sourceUsesContentLanguage
            ? $this->metadataEnricher->find($title, $authors, $language, $sourceSynopsis === null || ! $sourceUsesContentLanguage)
            : new WorkMetadata;
        $tagline = $sourceUsesContentLanguage ? $sourceTagline ?? $enrichment->tagline : $enrichment->tagline;
        $synopsis = $sourceUsesContentLanguage ? $sourceSynopsis ?? $enrichment->synopsis : $enrichment->synopsis;
        $usedEnrichmentSynopsis = $enrichment->synopsis !== null && $synopsis === $enrichment->synopsis;
        $literatureType = $this->literatureType($title, $categories, $synopsis, $requestedType);

        return new NormalizedLiterature(
            externalId: $externalId,
            title: $title,
            type: $literatureType,
            authors: $authors,
            categories: $categories,
            publicationYear: $publicationYear,
            tagline: $tagline,
            synopsis: $synopsis,
            publisher: $this->cleanText(Arr::get($item, 'volumeInfo.publisher')),
            language: $language,
            format: $this->format(Arr::get($item, 'volumeInfo.printType'), $literatureType),
            identifier: $this->identifier(Arr::get($item, 'volumeInfo.industryIdentifiers')),
            coverUrl: $this->coverUrl(Arr::get($item, 'volumeInfo.imageLinks')),
            originalTitle: $enrichment->originalTitle,
            synopsisSourceName: $usedEnrichmentSynopsis ? $enrichment->synopsisSourceName : null,
            synopsisSourceUrl: $usedEnrichmentSynopsis ? $enrichment->synopsisSourceUrl : null,
        );
    }

    /** @return list<string> */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->map(fn (mixed $value): ?string => $this->cleanText($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function identifier(mixed $identifiers): ?string
    {
        if (! is_array($identifiers)) {
            return null;
        }

        foreach (['ISBN_13', 'ISBN_10'] as $preferredType) {
            foreach ($identifiers as $identifier) {
                if (! is_array($identifier) || Arr::get($identifier, 'type') !== $preferredType) {
                    continue;
                }

                $value = $this->cleanText(Arr::get($identifier, 'identifier'));

                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function coverUrl(mixed $imageLinks): ?string
    {
        if (! is_array($imageLinks)) {
            return null;
        }

        foreach (['extraLarge', 'large', 'medium', 'small', 'thumbnail', 'smallThumbnail'] as $size) {
            $coverUrl = $this->cleanText(Arr::get($imageLinks, $size));

            if ($coverUrl !== null) {
                return preg_replace('#^http://#', 'https://', $coverUrl) ?? $coverUrl;
            }
        }

        return null;
    }

    /** @param list<string> $categories */
    private function literatureType(string $title, array $categories, ?string $synopsis, string $requestedType = 'all'): string
    {
        $normalizedTitle = Str::lower($title);
        $normalizedCategories = Str::lower(implode(' | ', $categories));
        $normalizedSynopsis = Str::lower($synopsis ?? '');

        if (preg_match('/\((?:light )?novel\)/u', $normalizedTitle) === 1) {
            return 'novel';
        }

        if (preg_match('/\b(?:non[- ]?fiction|nonfiksi|comics?|graphic novels?|literary criticism|literary collections)\b/u', $normalizedCategories) === 1) {
            return 'book';
        }

        if (preg_match('/\b(?:fiction|fiksi|novels?|romance|romansa|fantasy|fantasi)\b/u', $normalizedCategories) === 1) {
            return 'novel';
        }

        if (preg_match('/\b(?:adventure|children(?:\x{2019}|\x{0027})?s|young adult) stories\b/u', $normalizedCategories) === 1) {
            return 'novel';
        }

        if (preg_match('/\b(?:a novel|the novel|novel ini|sebuah novel)\b/u', $normalizedSynopsis) === 1) {
            return 'novel';
        }

        return $requestedType === 'novel' ? 'novel' : 'book';
    }

    private function format(mixed $printType, string $literatureType): string
    {
        return match (Str::upper((string) $printType)) {
            'MAGAZINE' => 'Magazine',
            default => $literatureType === 'novel' ? 'Novel' : 'Book',
        };
    }

    private function cleanText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $cleaned = Str::squish(html_entity_decode(strip_tags($value)));

        return $cleaned === '' ? null : $cleaned;
    }
}
