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
    public function __construct(private NovelCatalogClassifier $novelClassifier) {}

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

    private const SERIES_QUERY = <<<'GRAPHQL'
        query SeriesBooks($seriesIds: [Int!]!, $limit: Int!) {
          series(where: {id: {_in: $seriesIds}}) {
            id
            book_series(
              where: {
                book: {
                  book_status_id: {_eq: 1}
                  compilation: {_eq: false}
                  default_physical_edition: {language_id: {_eq: 1}}
                }
              }
              order_by: {position: asc}
              limit: $limit
            ) {
              position
              book {
                id
                title
                subtitle
                release_date
                cached_image
                contributions {
                  author {
                    name
                  }
                }
                editions(limit: 10) {
                  isbn_10
                  isbn_13
                }
              }
            }
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
        $cacheKey = 'literature-source:hardcover:v2:'.hash('sha256', Str::lower($query).":{$normalizedLimit}");
        $items = Cache::remember(
            $cacheKey,
            now()->addMinutes(max(1, (int) config('services.hardcover.cache_minutes', 30))),
            fn (): array => $this->request($query, $normalizedLimit, $token),
        );
        $seriesBooks = $this->seriesBooks($items, $token);

        return collect($items)
            ->map(function (mixed $item) use ($query, $seriesBooks): ?NormalizedLiterature {
                $seriesId = $this->featuredSeriesId($item);

                return $this->normalize(
                    $item,
                    $query,
                    $seriesId === null ? [] : ($seriesBooks[$seriesId] ?? []),
                );
            })
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

    /** @param list<array<string, mixed>> $seriesBooks */
    private function normalize(mixed $item, string $query, array $seriesBooks = []): ?NormalizedLiterature
    {
        if (! is_array($item)) {
            return null;
        }

        $externalId = $this->cleanText(Arr::get($item, 'id'));
        $title = $this->cleanText(Arr::get($item, 'title'));

        if ($externalId === null || $title === null || Str::length($title) > 255) {
            return null;
        }

        $authors = $this->stringList(Arr::get($item, 'author_names'));
        $isbns = $this->stringList(Arr::get($item, 'isbns'));
        $identifier = $this->identifier($isbns) ?? "HARDCOVER:{$externalId}";

        if (! $this->novelClassifier->accepts(
            title: $title,
            categories: [],
            description: $this->cleanText(Arr::get($item, 'subtitle')),
            authors: $authors,
            publisher: null,
            identifier: $identifier,
            query: $query,
        )) {
            return null;
        }

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
            identifier: $identifier,
            coverUrl: $this->coverUrl($item),
            relations: $this->seriesRelations($item, $seriesBooks),
        );
    }

    /**
     * @param  list<mixed>  $items
     * @return array<string, list<array<string, mixed>>>
     */
    private function seriesBooks(array $items, string $token): array
    {
        $seriesIds = collect($items)
            ->map(fn (mixed $item): ?string => $this->featuredSeriesId($item))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($seriesIds->isEmpty()) {
            return [];
        }

        $limit = max(2, min((int) config('services.hardcover.relationship_limit', 40), 100));
        $cacheKey = 'literature-source:hardcover:series:v1:'.hash(
            'sha256',
            $seriesIds->join(',').":{$limit}",
        );

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(max(1, (int) config('services.hardcover.cache_minutes', 30))),
            fn (): array => $this->requestSeriesBooks($seriesIds->map(fn (string $id): int => (int) $id)->all(), $limit, $token),
        );
    }

    /**
     * @param  list<int>  $seriesIds
     * @return array<string, list<array<string, mixed>>>
     */
    private function requestSeriesBooks(array $seriesIds, int $limit, string $token): array
    {
        try {
            $response = Http::acceptJson()
                ->withToken($token)
                ->withUserAgent((string) config('services.hardcover.user_agent'))
                ->connectTimeout((int) config('services.hardcover.connect_timeout', 3))
                ->timeout((int) config('services.hardcover.timeout', 10))
                ->post((string) config('services.hardcover.base_url'), [
                    'query' => self::SERIES_QUERY,
                    'variables' => [
                        'seriesIds' => $seriesIds,
                        'limit' => $limit,
                    ],
                ]);
        } catch (ConnectionException) {
            return [];
        }

        if ($response->failed() || (is_array($response->json('errors')) && $response->json('errors') !== [])) {
            return [];
        }

        $series = $response->json('data.series');

        if (! is_array($series)) {
            return [];
        }

        return collect($series)
            ->filter(fn (mixed $item): bool => is_array($item)
                && filled(Arr::get($item, 'id'))
                && is_array(Arr::get($item, 'book_series')))
            ->mapWithKeys(fn (array $item): array => [
                (string) Arr::get($item, 'id') => array_values(Arr::get($item, 'book_series')),
            ])
            ->all();
    }

    /** @param list<array<string, mixed>> $seriesBooks */
    private function seriesRelations(array $item, array $seriesBooks): array
    {
        $externalId = $this->cleanText(Arr::get($item, 'id'));

        if ($externalId === null || $seriesBooks === []) {
            return [];
        }

        $currentPosition = $this->seriesPosition(
            Arr::get($item, 'featured_series.position')
                ?? Arr::get($item, 'featured_series_position'),
        );
        $positions = collect($seriesBooks)
            ->map(fn (array $seriesBook): ?float => $this->seriesPosition(Arr::get($seriesBook, 'position')))
            ->filter(fn (?float $position): bool => $position !== null)
            ->unique()
            ->sort()
            ->values();
        $previousPosition = $currentPosition === null
            ? null
            : $positions->filter(fn (float $position): bool => $position < $currentPosition)->last();
        $nextPosition = $currentPosition === null
            ? null
            : $positions->first(fn (float $position): bool => $position > $currentPosition);

        return collect($seriesBooks)
            ->filter(fn (mixed $seriesBook): bool => is_array($seriesBook)
                && (string) Arr::get($seriesBook, 'book.id', '') !== $externalId)
            ->map(function (array $seriesBook) use ($previousPosition, $nextPosition): ?NormalizedLiteratureRelation {
                $book = Arr::get($seriesBook, 'book');

                if (! is_array($book)) {
                    return null;
                }

                $related = $this->normalize(
                    $this->seriesBookSearchItem($book),
                    (string) Arr::get($book, 'title', ''),
                );

                if ($related === null) {
                    return null;
                }

                $position = $this->seriesPosition(Arr::get($seriesBook, 'position'));
                $relationType = match (true) {
                    $position !== null && $previousPosition !== null && $position === $previousPosition => 'prequel',
                    $position !== null && $nextPosition !== null && $position === $nextPosition => 'sequel',
                    default => 'related',
                };

                return new NormalizedLiteratureRelation($relationType, $related);
            })
            ->filter()
            ->unique(fn (NormalizedLiteratureRelation $relation): string => $relation->literature->externalId)
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function seriesBookSearchItem(array $book): array
    {
        $releaseDate = $this->cleanText(Arr::get($book, 'release_date'));

        return [
            'id' => Arr::get($book, 'id'),
            'title' => Arr::get($book, 'title'),
            'subtitle' => Arr::get($book, 'subtitle'),
            'release_year' => $releaseDate === null ? null : substr($releaseDate, 0, 4),
            'author_names' => collect(Arr::get($book, 'contributions', []))
                ->map(fn (mixed $contribution): mixed => Arr::get($contribution, 'author.name'))
                ->filter()
                ->values()
                ->all(),
            'image' => Arr::get($book, 'cached_image'),
            'isbns' => collect(Arr::get($book, 'editions', []))
                ->flatMap(fn (mixed $edition): array => is_array($edition)
                    ? [Arr::get($edition, 'isbn_13'), Arr::get($edition, 'isbn_10')]
                    : [])
                ->filter()
                ->values()
                ->all(),
        ];
    }

    private function featuredSeriesId(mixed $item): ?string
    {
        if (! is_array($item)) {
            return null;
        }

        return $this->cleanText(Arr::get($item, 'featured_series.series.id'));
    }

    private function seriesPosition(mixed $position): ?float
    {
        return is_numeric($position) ? (float) $position : null;
    }

    /** @param array<string, mixed> $item */
    private function coverUrl(array $item): ?string
    {
        $image = Arr::get($item, 'image');

        return $this->secureUrl(is_array($image) ? Arr::get($image, 'url') : $image)
            ?? $this->secureUrl(Arr::get($item, 'image_url'))
            ?? $this->secureUrl(Arr::get($item, 'cover_url'));
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
