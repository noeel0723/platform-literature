<?php

namespace App\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class MangaDexAdapter
{
    /** @return Collection<int, NormalizedLiterature> */
    public function search(string $query, string $literatureType, int $limit = 6): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        if (! in_array($literatureType, ['all', 'manga', 'manhwa'], true)) {
            throw new InvalidArgumentException('MangaDex only supports all, manga, and manhwa catalog types.');
        }

        $normalizedLimit = max(1, min($limit, 100));
        $cacheKey = 'literature-source:mangadex:'.hash(
            'sha256',
            Str::lower($query).":{$literatureType}:{$normalizedLimit}",
        );

        $items = Cache::remember(
            $cacheKey,
            now()->addMinutes(max(1, (int) config('services.mangadex.cache_minutes', 30))),
            fn (): array => $this->request($query, $literatureType, $normalizedLimit),
        );

        return collect($items)
            ->map(fn (mixed $item): ?NormalizedLiterature => $this->normalize($item, $literatureType))
            ->filter()
            ->values();
    }

    /** @return list<mixed> */
    private function request(string $query, string $literatureType, int $limit): array
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('services.mangadex.base_url'), '/'))
                ->acceptJson()
                ->withUserAgent((string) config('services.mangadex.user_agent'))
                ->connectTimeout((int) config('services.mangadex.connect_timeout', 3))
                ->timeout((int) config('services.mangadex.timeout', 12))
                ->get('/manga', [
                    'title' => $query,
                    'limit' => $limit,
                    'includes' => ['author', 'artist', 'cover_art'],
                    'originalLanguage' => match ($literatureType) {
                        'manga' => ['ja'],
                        'manhwa' => ['ko'],
                        default => ['ja', 'ko'],
                    },
                    'contentRating' => ['safe', 'suggestive'],
                    'order' => ['relevance' => 'desc'],
                ]);
        } catch (ConnectionException $exception) {
            throw new LiteratureSourceUnavailable(
                'MangaDex',
                'MangaDex could not be reached.',
                $exception,
            );
        }

        if ($response->status() === 429) {
            throw new LiteratureSourceUnavailable(
                'MangaDex',
                'MangaDex rate limit was reached.',
            );
        }

        if ($response->failed()) {
            throw new LiteratureSourceUnavailable(
                'MangaDex',
                "MangaDex returned HTTP {$response->status()}.",
            );
        }

        if ($response->json('result') !== 'ok') {
            throw new LiteratureSourceUnavailable(
                'MangaDex',
                'MangaDex returned an invalid API response.',
            );
        }

        $items = $response->json('data');

        return is_array($items) ? array_values($items) : [];
    }

    private function normalize(mixed $item, string $requestedType): ?NormalizedLiterature
    {
        if (! is_array($item) || Arr::get($item, 'type') !== 'manga') {
            return null;
        }

        $externalId = $this->cleanText(Arr::get($item, 'id'));
        $attributes = Arr::get($item, 'attributes');

        if ($externalId === null || ! is_array($attributes)) {
            return null;
        }

        $originalLanguage = Str::lower((string) Arr::get($attributes, 'originalLanguage'));

        if (! in_array($originalLanguage, ['ja', 'ko'], true)
            || ($requestedType === 'manga' && $originalLanguage !== 'ja')
            || ($requestedType === 'manhwa' && $originalLanguage !== 'ko')) {
            return null;
        }

        $title = $this->localizedText(
            Arr::get($attributes, 'title'),
            ['en', "{$originalLanguage}-ro", $originalLanguage],
        );

        if ($title === null) {
            return null;
        }

        return new NormalizedLiterature(
            externalId: $externalId,
            title: $title,
            type: $originalLanguage === 'ko' ? 'manhwa' : 'manga',
            authors: $this->authors(Arr::get($item, 'relationships')),
            categories: $this->categories(Arr::get($attributes, 'tags')),
            publicationYear: $this->publicationYear(Arr::get($attributes, 'year')),
            tagline: null,
            synopsis: $this->localizedText(Arr::get($attributes, 'description'), ['en'], false),
            publisher: null,
            language: $originalLanguage,
            format: $originalLanguage === 'ko' ? 'Manhwa' : 'Manga',
            identifier: "MANGADEX:{$externalId}",
            coverUrl: $this->coverUrl($externalId, Arr::get($item, 'relationships')),
            originalTitle: $this->originalTitle($attributes, $originalLanguage, $title),
        );
    }

    /** @return list<string> */
    private function authors(mixed $relationships): array
    {
        if (! is_array($relationships)) {
            return [];
        }

        $namesByRole = collect($relationships)
            ->filter(fn (mixed $relationship): bool => is_array($relationship)
                && in_array(Arr::get($relationship, 'type'), ['author', 'artist'], true))
            ->groupBy(fn (array $relationship): string => (string) Arr::get($relationship, 'type'))
            ->map(fn (Collection $relationships): array => $relationships
                ->map(fn (array $relationship): ?string => $this->cleanText(Arr::get($relationship, 'attributes.name')))
                ->filter()
                ->unique()
                ->values()
                ->all());

        $authors = $namesByRole->get('author', []);

        return $authors !== [] ? $authors : $namesByRole->get('artist', []);
    }

    /** @return list<string> */
    private function categories(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        return collect($tags)
            ->map(fn (mixed $tag): ?string => is_array($tag)
                ? $this->localizedText(Arr::get($tag, 'attributes.name'), ['en'])
                : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function originalTitle(array $attributes, string $originalLanguage, string $displayTitle): ?string
    {
        $originalTitle = $this->localizedText(
            Arr::get($attributes, 'title'),
            [$originalLanguage, "{$originalLanguage}-ro"],
            false,
        );

        if ($originalTitle === null) {
            foreach ((array) Arr::get($attributes, 'altTitles', []) as $alternativeTitle) {
                $originalTitle = $this->localizedText(
                    $alternativeTitle,
                    [$originalLanguage, "{$originalLanguage}-ro"],
                    false,
                );

                if ($originalTitle !== null) {
                    break;
                }
            }
        }

        return $originalTitle !== null && $originalTitle !== $displayTitle ? $originalTitle : null;
    }

    private function coverUrl(string $mangaId, mixed $relationships): ?string
    {
        if (! is_array($relationships)) {
            return null;
        }

        $cover = collect($relationships)->first(
            fn (mixed $relationship): bool => is_array($relationship)
                && Arr::get($relationship, 'type') === 'cover_art',
        );
        $fileName = is_array($cover)
            ? $this->cleanText(Arr::get($cover, 'attributes.fileName'))
            : null;

        if ($fileName === null) {
            return null;
        }

        return rtrim((string) config('services.mangadex.covers_url'), '/')
            ."/{$mangaId}/{$fileName}";
    }

    /** @param list<string> $preferredLanguages */
    private function localizedText(
        mixed $translations,
        array $preferredLanguages,
        bool $useAnyLanguageAsFallback = true,
    ): ?string {
        if (! is_array($translations)) {
            return null;
        }

        foreach ($preferredLanguages as $language) {
            $value = $this->cleanText(Arr::get($translations, $language));

            if ($value !== null) {
                return $value;
            }
        }

        if ($useAnyLanguageAsFallback) {
            foreach ($translations as $value) {
                $cleaned = $this->cleanText($value);

                if ($cleaned !== null) {
                    return $cleaned;
                }
            }
        }

        return null;
    }

    private function publicationYear(mixed $year): ?int
    {
        if (! is_int($year) && ! is_numeric($year)) {
            return null;
        }

        $normalizedYear = (int) $year;

        return $normalizedYear > 0 ? $normalizedYear : null;
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
