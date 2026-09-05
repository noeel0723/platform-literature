<?php

namespace App\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class AniListAdapter
{
    /** @return Collection<int, NormalizedLiterature> */
    public function search(string $query, string $literatureType, int $limit = 6): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        $formats = match ($literatureType) {
            'manga' => ['MANGA', 'ONE_SHOT'],
            'light-novel' => ['NOVEL'],
            'all' => ['MANGA', 'ONE_SHOT', 'NOVEL'],
            default => throw new InvalidArgumentException('AniList only supports all, manga, and light-novel catalog types.'),
        };

        try {
            $response = Http::acceptJson()
                ->connectTimeout((int) config('services.anilist.connect_timeout', 3))
                ->timeout((int) config('services.anilist.timeout', 8))
                ->post((string) config('services.anilist.base_url'), [
                    'query' => $this->searchQuery(),
                    'variables' => [
                        'search' => $query,
                        'perPage' => max(1, min($limit, 50)),
                        'formats' => $formats,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new LiteratureSourceUnavailable(
                'AniList',
                'AniList could not be reached.',
                $exception,
            );
        }

        if ($response->status() === 429) {
            throw new LiteratureSourceUnavailable(
                'AniList',
                'AniList rate limit was reached.',
            );
        }

        if ($response->failed()) {
            throw new LiteratureSourceUnavailable(
                'AniList',
                "AniList returned HTTP {$response->status()}.",
            );
        }

        if (is_array($response->json('errors')) && $response->json('errors') !== []) {
            throw new LiteratureSourceUnavailable(
                'AniList',
                'AniList returned a GraphQL error.',
            );
        }

        $items = $response->json('data.Page.media');

        if (! is_array($items)) {
            return collect();
        }

        return collect($items)
            ->map(fn (mixed $item): ?NormalizedLiterature => $this->normalize($item, $literatureType))
            ->filter()
            ->values();
    }

    private function normalize(mixed $item, string $literatureType): ?NormalizedLiterature
    {
        if (! is_array($item)) {
            return null;
        }

        $externalId = trim((string) Arr::get($item, 'id', ''));
        $title = $this->cleanText(
            Arr::get($item, 'title.english')
                ?? Arr::get($item, 'title.romaji')
                ?? Arr::get($item, 'title.native'),
        );

        if ($externalId === '' || $title === null) {
            return null;
        }

        $coverUrl = $this->cleanText(
            Arr::get($item, 'coverImage.extraLarge')
                ?? Arr::get($item, 'coverImage.large')
                ?? Arr::get($item, 'coverImage.medium'),
        );

        return new NormalizedLiterature(
            externalId: $externalId,
            title: $title,
            type: $this->literatureType(Arr::get($item, 'format'), $literatureType),
            authors: $this->authors(Arr::get($item, 'staff.edges')),
            categories: $this->stringList(Arr::get($item, 'genres')),
            publicationYear: $this->publicationYear(Arr::get($item, 'startDate.year')),
            tagline: null,
            synopsis: $this->cleanText(Arr::get($item, 'description')),
            publisher: null,
            language: null,
            format: $this->format(Arr::get($item, 'format')),
            identifier: "ANILIST:{$externalId}",
            coverUrl: $coverUrl,
        );
    }

    /** @return list<string> */
    private function authors(mixed $edges): array
    {
        if (! is_array($edges)) {
            return [];
        }

        return collect($edges)
            ->filter(fn (mixed $edge): bool => is_array($edge)
                && Str::contains(Str::lower((string) Arr::get($edge, 'role')), 'story'))
            ->map(fn (array $edge): ?string => $this->cleanText(Arr::get($edge, 'node.name.full')))
            ->filter()
            ->unique()
            ->values()
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

    private function publicationYear(mixed $year): ?int
    {
        if (! is_int($year) && ! is_numeric($year)) {
            return null;
        }

        $normalizedYear = (int) $year;

        return $normalizedYear > 0 ? $normalizedYear : null;
    }

    private function format(mixed $format): ?string
    {
        $normalizedFormat = Str::upper((string) $format);

        return match ($normalizedFormat) {
            'MANGA' => 'Manga',
            'NOVEL' => 'Light Novel',
            'ONE_SHOT' => 'One-shot',
            '' => null,
            default => Str::headline(Str::lower($normalizedFormat)),
        };
    }

    private function literatureType(mixed $format, string $requestedType): string
    {
        return match (Str::upper((string) $format)) {
            'NOVEL' => 'light-novel',
            'MANGA', 'ONE_SHOT' => 'manga',
            default => $requestedType === 'light-novel' ? 'light-novel' : 'manga',
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

    private function searchQuery(): string
    {
        return <<<'GRAPHQL'
            query SearchLiterature($search: String!, $perPage: Int!, $formats: [MediaFormat]) {
              Page(page: 1, perPage: $perPage) {
                media(
                  search: $search
                  type: MANGA
                  format_in: $formats
                  isAdult: false
                  sort: SEARCH_MATCH
                ) {
                  id
                  title {
                    romaji
                    english
                    native
                  }
                  description(asHtml: false)
                  startDate {
                    year
                  }
                  genres
                  coverImage {
                    extraLarge
                    large
                    medium
                  }
                  format
                  staff(perPage: 10) {
                    edges {
                      role
                      node {
                        name {
                          full
                        }
                      }
                    }
                  }
                }
              }
            }
            GRAPHQL;
    }
}
