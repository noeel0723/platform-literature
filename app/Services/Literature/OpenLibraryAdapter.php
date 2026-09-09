<?php

namespace App\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class OpenLibraryAdapter
{
    /** @return Collection<int, NormalizedLiterature> */
    public function search(string $query, int $limit = 6): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        $normalizedLimit = max(1, min($limit, 100));
        $cacheKey = 'literature-source:open-library:v2:'.hash(
            'sha256',
            Str::lower($query).":{$normalizedLimit}",
        );
        $items = Cache::remember(
            $cacheKey,
            now()->addMinutes(max(1, (int) config('services.open_library.cache_minutes', 60))),
            fn (): array => $this->request($query, $normalizedLimit),
        );

        return collect($items)
            ->map(fn (mixed $item): ?NormalizedLiterature => $this->normalize($item))
            ->filter()
            ->values();
    }

    /** @return list<mixed> */
    private function request(string $query, int $limit): array
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('services.open_library.base_url'), '/'))
                ->acceptJson()
                ->withUserAgent((string) config('services.open_library.user_agent'))
                ->connectTimeout((int) config('services.open_library.connect_timeout', 3))
                ->timeout((int) config('services.open_library.timeout', 10))
                ->get('/search.json', [
                    'q' => $query,
                    'lang' => 'en',
                    'limit' => $limit,
                    'fields' => implode(',', [
                        'key',
                        'title',
                        'author_name',
                        'author_key',
                        'first_publish_year',
                        'cover_i',
                        'isbn',
                        'publisher',
                        'language',
                        'subject',
                        'first_sentence',
                        'editions',
                        'editions.key',
                        'editions.title',
                        'editions.language',
                    ]),
                ]);
        } catch (ConnectionException $exception) {
            throw new LiteratureSourceUnavailable(
                'Open Library',
                'Open Library could not be reached.',
                $exception,
            );
        }

        if ($response->status() === 429) {
            throw new LiteratureSourceUnavailable('Open Library', 'Open Library rate limit was reached.');
        }

        if ($response->failed()) {
            throw new LiteratureSourceUnavailable(
                'Open Library',
                "Open Library returned HTTP {$response->status()}.",
            );
        }

        $items = $response->json('docs');

        if (! is_array($items)) {
            throw new LiteratureSourceUnavailable('Open Library', 'Open Library returned an invalid search response.');
        }

        return array_values($items);
    }

    private function normalize(mixed $item): ?NormalizedLiterature
    {
        if (! is_array($item)) {
            return null;
        }

        $externalId = $this->openLibraryId(Arr::get($item, 'key'));
        $sourceTitle = $this->cleanText(Arr::get($item, 'title'));
        $englishEdition = $this->englishEdition(Arr::get($item, 'editions.docs'));
        $englishTitle = $this->cleanText(Arr::get($englishEdition, 'title'));

        if ($externalId === null || $sourceTitle === null) {
            return null;
        }

        $authorDetails = $this->authors(
            Arr::get($item, 'author_name'),
            Arr::get($item, 'author_key'),
        );

        return new NormalizedLiterature(
            externalId: $externalId,
            title: $sourceTitle,
            type: 'novel',
            authors: collect($authorDetails)->pluck('name')->all(),
            categories: array_slice($this->stringList(Arr::get($item, 'subject')), 0, 12),
            publicationYear: $this->publicationYear(Arr::get($item, 'first_publish_year')),
            tagline: null,
            synopsis: $this->firstSentence(Arr::get($item, 'first_sentence')),
            publisher: $this->stringList(Arr::get($item, 'publisher'))[0] ?? null,
            language: 'en',
            format: 'Novel',
            identifier: $this->identifier(Arr::get($item, 'isbn')) ?? "OPENLIBRARY:{$externalId}",
            coverUrl: $this->coverUrl(Arr::get($item, 'cover_i')),
            originalTitle: $englishTitle !== null && $englishTitle !== $sourceTitle ? $englishTitle : null,
            authorDetails: $authorDetails,
        );
    }

    /** @return array<string, mixed> */
    private function englishEdition(mixed $editions): array
    {
        if (! is_array($editions)) {
            return [];
        }

        $edition = collect($editions)->first(function (mixed $edition): bool {
            if (! is_array($edition)) {
                return false;
            }

            return collect((array) Arr::get($edition, 'language'))
                ->contains(fn (mixed $language): bool => in_array($language, ['eng', 'en'], true));
        });

        return is_array($edition) ? $edition : [];
    }

    /** @return list<NormalizedAuthor> */
    private function authors(mixed $names, mixed $keys): array
    {
        $authorNames = $this->stringList($names);
        $authorKeys = $this->stringList($keys);

        return collect($authorNames)
            ->map(function (string $name, int $position) use ($authorKeys): NormalizedAuthor {
                $externalId = $this->openLibraryId($authorKeys[$position] ?? null);

                return new NormalizedAuthor(
                    name: $name,
                    sourceUrl: $externalId === null ? null : "https://openlibrary.org/authors/{$externalId}",
                    externalId: $externalId,
                );
            })
            ->all();
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
        $normalizedIdentifiers = collect($this->stringList($identifiers))
            ->map(fn (string $identifier): string => Str::upper(preg_replace('/[^0-9X]/i', '', $identifier) ?? ''));

        return $normalizedIdentifiers
            ->first(fn (string $identifier): bool => preg_match('/^\d{13}$/', $identifier) === 1)
            ?? $normalizedIdentifiers
                ->first(fn (string $identifier): bool => preg_match('/^\d{9}[\dX]$/', $identifier) === 1);
    }

    private function coverUrl(mixed $coverId): ?string
    {
        if (! is_int($coverId) && ! is_numeric($coverId)) {
            return null;
        }

        return rtrim((string) config('services.open_library.covers_url'), '/')
            .'/b/id/'.(int) $coverId.'-L.jpg?default=false';
    }

    private function firstSentence(mixed $sentence): ?string
    {
        if (is_array($sentence)) {
            $sentence = Arr::first($sentence);
        }

        return $this->cleanText($sentence);
    }

    private function publicationYear(mixed $year): ?int
    {
        if (! is_int($year) && ! is_numeric($year)) {
            return null;
        }

        $normalizedYear = (int) $year;

        return $normalizedYear > 0 ? $normalizedYear : null;
    }

    private function openLibraryId(mixed $value): ?string
    {
        $id = $this->cleanText($value);

        return $id === null ? null : Str::afterLast($id, '/');
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
