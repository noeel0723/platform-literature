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
            'manhwa' => ['MANGA', 'ONE_SHOT'],
            'light-novel' => ['NOVEL'],
            'all' => ['MANGA', 'ONE_SHOT', 'NOVEL'],
            default => throw new InvalidArgumentException('AniList only supports all, manga, manhwa, and light-novel catalog types.'),
        };

        $countryOfOrigin = $literatureType === 'manhwa' ? 'KR' : null;
        $excludedCountries = $literatureType === 'manga' ? ['KR'] : null;

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
                        'countryOfOrigin' => $countryOfOrigin,
                        'excludedCountries' => $excludedCountries,
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

    private function normalize(mixed $item, string $literatureType, bool $includeRelations = true): ?NormalizedLiterature
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

        $countryOfOrigin = $this->cleanText(Arr::get($item, 'countryOfOrigin'));
        $authorDetails = $this->authors(Arr::get($item, 'staff.edges'));

        return new NormalizedLiterature(
            externalId: $externalId,
            title: $title,
            type: $this->literatureType(Arr::get($item, 'format'), $countryOfOrigin, $literatureType),
            authors: collect($authorDetails)->pluck('name')->all(),
            categories: $this->stringList(Arr::get($item, 'genres')),
            publicationYear: $this->publicationYear(Arr::get($item, 'startDate.year')),
            tagline: null,
            synopsis: $this->cleanText(Arr::get($item, 'description')),
            publisher: null,
            language: null,
            format: $this->format(Arr::get($item, 'format'), $countryOfOrigin),
            identifier: "ANILIST:{$externalId}",
            coverUrl: $coverUrl,
            originalTitle: $this->originalTitle($item, $title),
            relations: $includeRelations ? $this->relations(Arr::get($item, 'relations.edges')) : [],
            authorDetails: $authorDetails,
        );
    }

    /** @return list<NormalizedLiteratureRelation> */
    private function relations(mixed $edges): array
    {
        if (! is_array($edges)) {
            return [];
        }

        return collect($edges)
            ->filter(fn (mixed $edge): bool => is_array($edge)
                && Str::upper((string) Arr::get($edge, 'node.type')) === 'MANGA')
            ->map(function (array $edge): ?NormalizedLiteratureRelation {
                $literature = $this->normalize(Arr::get($edge, 'node'), 'all', false);

                if ($literature === null) {
                    return null;
                }

                return new NormalizedLiteratureRelation(
                    type: $this->relationType(Arr::get($edge, 'relationType')),
                    literature: $literature,
                );
            })
            ->filter()
            ->values()
            ->all();
    }

    private function relationType(mixed $relationType): string
    {
        return match (Str::upper((string) $relationType)) {
            'SEQUEL' => 'sequel',
            'PREQUEL' => 'prequel',
            'SPIN_OFF' => 'spin_off',
            'ADAPTATION' => 'adaptation',
            'SOURCE' => 'source',
            'SIDE_STORY' => 'side_story',
            'ALTERNATIVE' => 'alternative_version',
            default => 'related',
        };
    }

    private function originalTitle(array $item, string $displayTitle): ?string
    {
        $nativeTitle = $this->cleanText(Arr::get($item, 'title.native'));

        return $nativeTitle !== null && $nativeTitle !== $displayTitle ? $nativeTitle : null;
    }

    /** @return list<NormalizedAuthor> */
    private function authors(mixed $edges): array
    {
        if (! is_array($edges)) {
            return [];
        }

        return collect($edges)
            ->filter(fn (mixed $edge): bool => is_array($edge)
                && Str::contains(Str::lower((string) Arr::get($edge, 'role')), 'story'))
            ->map(function (array $edge): ?NormalizedAuthor {
                $name = $this->cleanText(Arr::get($edge, 'node.name.full'));

                if ($name === null) {
                    return null;
                }

                return new NormalizedAuthor(
                    name: $name,
                    imageUrl: $this->cleanUrl(
                        Arr::get($edge, 'node.image.large')
                            ?? Arr::get($edge, 'node.image.medium'),
                    ),
                    biography: $this->cleanText(Arr::get($edge, 'node.description')),
                    sourceUrl: $this->cleanUrl(Arr::get($edge, 'node.siteUrl')),
                    externalId: filled(Arr::get($edge, 'node.id'))
                        ? (string) Arr::get($edge, 'node.id')
                        : null,
                );
            })
            ->filter()
            ->unique(fn (NormalizedAuthor $author): string => Str::lower($author->name))
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

    private function format(mixed $format, ?string $countryOfOrigin): ?string
    {
        $normalizedFormat = Str::upper((string) $format);

        if ($countryOfOrigin === 'KR' && in_array($normalizedFormat, ['MANGA', 'ONE_SHOT'], true)) {
            return $normalizedFormat === 'ONE_SHOT' ? 'One-shot Manhwa' : 'Manhwa';
        }

        return match ($normalizedFormat) {
            'MANGA' => 'Manga',
            'NOVEL' => 'Light Novel',
            'ONE_SHOT' => 'One-shot',
            '' => null,
            default => Str::headline(Str::lower($normalizedFormat)),
        };
    }

    private function literatureType(mixed $format, ?string $countryOfOrigin, string $requestedType): string
    {
        return match (Str::upper((string) $format)) {
            'NOVEL' => 'light-novel',
            'MANGA', 'ONE_SHOT' => $countryOfOrigin === 'KR' ? 'manhwa' : 'manga',
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

    private function cleanUrl(mixed $value): ?string
    {
        $url = $this->cleanText($value);

        if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return preg_replace('#^http://#', 'https://', $url) ?? $url;
    }

    private function searchQuery(): string
    {
        return <<<'GRAPHQL'
            query SearchLiterature($search: String!, $perPage: Int!, $formats: [MediaFormat], $countryOfOrigin: CountryCode, $excludedCountries: [CountryCode]) {
              Page(page: 1, perPage: $perPage) {
                media(
                  search: $search
                  type: MANGA
                  format_in: $formats
                  countryOfOrigin: $countryOfOrigin
                  countryOfOrigin_not_in: $excludedCountries
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
                  countryOfOrigin
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
                        id
                        name {
                          full
                        }
                        image {
                          large
                          medium
                        }
                        description(asHtml: false)
                        siteUrl
                      }
                    }
                  }
                  relations {
                    edges {
                      relationType(version: 2)
                      node {
                        id
                        type
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
                        countryOfOrigin
                        coverImage {
                          extraLarge
                          large
                          medium
                        }
                        format
                      }
                    }
                  }
                }
              }
            }
            GRAPHQL;
    }
}
