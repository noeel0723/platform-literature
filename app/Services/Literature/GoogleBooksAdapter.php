<?php

namespace App\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class GoogleBooksAdapter
{
    /** @return Collection<int, NormalizedLiterature> */
    public function search(string $query, int $limit = 6): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
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
                    'q' => $query,
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
            ->map(fn (mixed $item): ?NormalizedLiterature => $this->normalize($item))
            ->filter()
            ->values();
    }

    private function normalize(mixed $item): ?NormalizedLiterature
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

        $coverUrl = $this->cleanText(
            Arr::get($item, 'volumeInfo.imageLinks.thumbnail')
                ?? Arr::get($item, 'volumeInfo.imageLinks.smallThumbnail'),
        );

        if ($coverUrl !== null) {
            $coverUrl = preg_replace('#^http://#', 'https://', $coverUrl) ?? $coverUrl;
        }

        return new NormalizedLiterature(
            externalId: $externalId,
            title: $title,
            authors: $this->stringList(Arr::get($item, 'volumeInfo.authors')),
            categories: $this->stringList(Arr::get($item, 'volumeInfo.categories')),
            publicationYear: $publicationYear,
            tagline: $this->cleanText(Arr::get($item, 'volumeInfo.subtitle')),
            synopsis: $this->cleanText(Arr::get($item, 'volumeInfo.description')),
            publisher: $this->cleanText(Arr::get($item, 'volumeInfo.publisher')),
            language: $this->cleanText(Arr::get($item, 'volumeInfo.language')),
            format: $this->format(Arr::get($item, 'volumeInfo.printType')),
            identifier: $this->identifier(Arr::get($item, 'volumeInfo.industryIdentifiers')),
            coverUrl: $coverUrl,
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

    private function format(mixed $printType): string
    {
        return match (Str::upper((string) $printType)) {
            'MAGAZINE' => 'Majalah',
            default => 'Buku',
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
