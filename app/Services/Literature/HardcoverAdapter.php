<?php

namespace App\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class HardcoverAdapter
{
    private const SEARCH_QUERY = <<<'GRAPHQL'
        query SearchBooks($query: String!, $perPage: Int!) {
          search(
            query: $query
            query_type: "books"
            per_page: $perPage
            page: 1
            sort: "activities_count:desc"
          ) {
            results
          }
        }
        GRAPHQL;

    /** @return Collection<int, NormalizedLiterature> */
    public function search(string $query, int $limit = 6): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        $token = trim((string) config('services.hardcover.token'));

        if ($token === '') {
            throw new LiteratureSourceUnavailable('Hardcover', 'Hardcover API token is not configured.');
        }

        $normalizedLimit = max(1, min($limit, 40));
        $cacheKey = 'literature-source:hardcover:v1:'.hash('sha256', Str::lower($query).":{$normalizedLimit}");
        $items = Cache::remember(
            $cacheKey,
            now()->addMinutes(max(1, (int) config('services.hardcover.cache_minutes', 30))),
            fn (): array => $this->request($query, $normalizedLimit, $token),
        );

        return collect($items)
            ->map(fn (mixed $item): ?NormalizedLiterature => $this->normalize($item))
            ->filter()
            ->values();
    }

    /** @return list<mixed> */
    private function request(string $query, int $limit, string $token): array
    {
        try {
            $response = Http::acceptJson()
                ->withToken($token)
                ->withUserAgent((string) config('services.hardcover.user_agent'))
                ->connectTimeout((int) config('services.hardcover.connect_timeout', 3))
                ->timeout((int) config('services.hardcover.timeout', 10))
                ->post((string) config('services.hardcover.base_url'), [
                    'query' => self::SEARCH_QUERY,
                    'variables' => [
                        'query' => $query,
                        'perPage' => $limit,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new LiteratureSourceUnavailable('Hardcover', 'Hardcover could not be reached.', $exception);
        }

        if ($response->status() === 429) {
            throw new LiteratureSourceUnavailable('Hardcover', 'Hardcover rate limit was reached.');
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new LiteratureSourceUnavailable('Hardcover', 'Hardcover rejected the configured API token.');
        }

        if ($response->failed()) {
            throw new LiteratureSourceUnavailable('Hardcover', "Hardcover returned HTTP {$response->status()}.");
        }

        if (is_array($response->json('errors')) && $response->json('errors') !== []) {
            throw new LiteratureSourceUnavailable('Hardcover', 'Hardcover returned a GraphQL error.');
        }

        $items = $this->searchResults($response->json('data.search.results'));

        if ($items === null) {
            throw new LiteratureSourceUnavailable('Hardcover', 'Hardcover returned an invalid search response.');
        }

        return $items;
    }

    /** @return list<mixed>|null */
    private function searchResults(mixed $results): ?array
    {
        if (! is_array($results)) {
            return null;
        }

        if (array_is_list($results)) {
            return array_values($results);
        }

        $hits = Arr::get($results, 'hits');

        if (! is_array($hits)) {
            return null;
        }

        return collect($hits)
            ->map(fn (mixed $hit): mixed => is_array($hit) ? Arr::get($hit, 'document') : null)
            ->filter(fn (mixed $document): bool => is_array($document))
            ->values()
            ->all();
    }

    private function normalize(mixed $item): ?NormalizedLiterature
    {
        if (! is_array($item)) {
            return null;
        }

        $externalId = $this->cleanText(Arr::get($item, 'id'));
        $title = $this->cleanText(Arr::get($item, 'title'));

        if ($externalId === null || $title === null) {
            return null;
        }

        $authors = $this->stringList(Arr::get($item, 'author_names'));
        $isbns = $this->stringList(Arr::get($item, 'isbns'));

        return new NormalizedLiterature(
            externalId: $externalId,
            title: $title,
            type: 'novel',
            authors: $authors,
            categories: [],
            publicationYear: $this->year(Arr::get($item, 'release_year')),
            tagline: $this->cleanText(Arr::get($item, 'subtitle')),
            synopsis: null,
            publisher: null,
            language: 'en',
            format: 'Novel',
            identifier: $this->identifier($isbns) ?? "HARDCOVER:{$externalId}",
            coverUrl: $this->secureUrl(Arr::get($item, 'image')),
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

    /** @param list<string> $isbns */
    private function identifier(array $isbns): ?string
    {
        $normalized = collect($isbns)
            ->map(fn (string $isbn): string => Str::upper(preg_replace('/[^0-9X]/i', '', $isbn) ?? ''));

        return $normalized->first(fn (string $isbn): bool => preg_match('/^\d{13}$/', $isbn) === 1)
            ?? $normalized->first(fn (string $isbn): bool => preg_match('/^\d{9}[\dX]$/', $isbn) === 1);
    }

    private function year(mixed $year): ?int
    {
        if (! is_int($year) && ! is_numeric($year)) {
            return null;
        }

        $normalized = (int) $year;

        return $normalized > 0 ? $normalized : null;
    }

    private function secureUrl(mixed $value): ?string
    {
        $url = $this->cleanText($value);

        return $url === null ? null : (preg_replace('#^http://#', 'https://', $url) ?? $url);
    }

    private function cleanText(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $cleaned = Str::squish(html_entity_decode(strip_tags((string) $value)));

        return $cleaned === '' ? null : $cleaned;
    }
}
