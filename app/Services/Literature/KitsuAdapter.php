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

final class KitsuAdapter
{
    /** @return Collection<int, NormalizedLiterature> */
    public function search(string $query, string $literatureType, int $limit = 6): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        if (! in_array($literatureType, ['all', 'manga', 'manhwa'], true)) {
            throw new InvalidArgumentException('Kitsu only supports all, manga, and manhwa catalog types.');
        }

        $normalizedLimit = max(1, min($limit, 20));
        $cacheKey = 'literature-source:kitsu:'.hash(
            'sha256',
            Str::lower($query).":{$literatureType}:{$normalizedLimit}",
        );

        $payload = Cache::remember(
            $cacheKey,
            now()->addMinutes(max(1, (int) config('services.kitsu.cache_minutes', 30))),
            fn (): array => $this->request($query, $normalizedLimit),
        );

        $included = is_array($payload['included'] ?? null) ? $payload['included'] : [];

        return collect($payload['data'] ?? [])
            ->map(fn (mixed $item): ?NormalizedLiterature => $this->normalize($item, $included, $literatureType))
            ->filter()
            ->values();
    }

    /** @return array{data: list<mixed>, included: list<mixed>} */
    private function request(string $query, int $limit): array
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('services.kitsu.base_url'), '/'))
                ->withHeaders(['Accept' => 'application/vnd.api+json'])
                ->withUserAgent((string) config('services.kitsu.user_agent'))
                ->connectTimeout((int) config('services.kitsu.connect_timeout', 3))
                ->timeout((int) config('services.kitsu.timeout', 12))
                ->get('/manga', [
                    'filter[text]' => $query,
                    'page[limit]' => $limit,
                    'include' => 'staff.person',
                ]);
        } catch (ConnectionException $exception) {
            throw new LiteratureSourceUnavailable(
                'Kitsu',
                'Kitsu could not be reached.',
                $exception,
            );
        }

        if ($response->status() === 429) {
            throw new LiteratureSourceUnavailable('Kitsu', 'Kitsu rate limit was reached.');
        }

        if ($response->failed()) {
            throw new LiteratureSourceUnavailable('Kitsu', "Kitsu returned HTTP {$response->status()}.");
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new LiteratureSourceUnavailable('Kitsu', 'Kitsu returned an invalid JSON:API response.');
        }

        $included = $response->json('included');

        return [
            'data' => array_values($data),
            'included' => is_array($included) ? array_values($included) : [],
        ];
    }

    /** @param list<mixed> $included */
    private function normalize(mixed $item, array $included, string $requestedType): ?NormalizedLiterature
    {
        if (! is_array($item) || Arr::get($item, 'type') !== 'manga') {
            return null;
        }

        $externalId = $this->cleanText(Arr::get($item, 'id'));
        $attributes = Arr::get($item, 'attributes');

        if ($externalId === null || ! is_array($attributes)) {
            return null;
        }

        $type = Str::lower((string) (Arr::get($attributes, 'subtype') ?: Arr::get($attributes, 'mangaType')));

        if (! in_array($type, ['manga', 'manhwa'], true)
            || ($requestedType !== 'all' && $type !== $requestedType)) {
            return null;
        }

        $title = $this->localizedText(
            Arr::get($attributes, 'titles'),
            ['en', 'en_us', 'en_jp'],
        ) ?? $this->cleanText(Arr::get($attributes, 'canonicalTitle'));

        if ($title === null) {
            return null;
        }

        $originalLanguage = $type === 'manhwa' ? 'ko' : 'ja';
        $authorDetails = $this->authors($item, $included);

        return new NormalizedLiterature(
            externalId: $externalId,
            title: $title,
            type: $type,
            authors: collect($authorDetails)->pluck('name')->all(),
            categories: [],
            publicationYear: $this->publicationYear(Arr::get($attributes, 'startDate')),
            tagline: null,
            synopsis: $this->cleanText(
                Arr::get($attributes, 'synopsis') ?: Arr::get($attributes, 'description'),
            ),
            publisher: null,
            language: $originalLanguage,
            format: $type === 'manhwa' ? 'Manhwa' : 'Manga',
            identifier: "KITSU:{$externalId}",
            coverUrl: $this->cleanText(
                Arr::get($attributes, 'posterImage.medium')
                    ?: Arr::get($attributes, 'posterImage.small')
                    ?: Arr::get($attributes, 'posterImage.original'),
            ),
            originalTitle: $this->originalTitle($attributes, $originalLanguage, $title),
            authorDetails: $authorDetails,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<mixed>  $included
     * @return list<NormalizedAuthor>
     */
    private function authors(array $item, array $included): array
    {
        $staffIds = collect(Arr::get($item, 'relationships.staff.data', []))
            ->filter(fn (mixed $staff): bool => is_array($staff) && Arr::get($staff, 'type') === 'mediaStaff')
            ->map(fn (array $staff): ?string => $this->cleanText(Arr::get($staff, 'id')))
            ->filter()
            ->all();

        if ($staffIds === []) {
            return [];
        }

        $includedItems = collect($included)->filter(fn (mixed $includedItem): bool => is_array($includedItem));
        $people = $includedItems
            ->where('type', 'people')
            ->mapWithKeys(fn (array $person): array => [
                (string) Arr::get($person, 'id') => $person,
            ]);

        return $includedItems
            ->where('type', 'mediaStaff')
            ->whereIn('id', $staffIds)
            ->filter(function (array $staff): bool {
                $role = Str::lower((string) Arr::get($staff, 'attributes.role'));

                return Str::contains($role, ['story', 'art', 'author', 'creator', 'original']);
            })
            ->map(function (array $staff) use ($people): ?NormalizedAuthor {
                $person = $people->get((string) Arr::get($staff, 'relationships.person.data.id'));

                if (! is_array($person)) {
                    return null;
                }

                $name = $this->cleanText(Arr::get($person, 'attributes.name'));
                $externalId = $this->cleanText(Arr::get($person, 'id'));

                if ($name === null || $externalId === null) {
                    return null;
                }

                return new NormalizedAuthor(
                    name: $name,
                    imageUrl: $this->cleanText(
                        Arr::get($person, 'attributes.image.original')
                            ?: Arr::get($person, 'attributes.image.medium')
                            ?: Arr::get($person, 'attributes.image.small'),
                    ),
                    biography: $this->cleanText(Arr::get($person, 'attributes.description')),
                    externalId: $externalId,
                );
            })
            ->filter()
            ->unique(fn (NormalizedAuthor $author): string => $author->externalId ?? Str::lower($author->name))
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $attributes */
    private function originalTitle(array $attributes, string $language, string $displayTitle): ?string
    {
        $originalTitle = $this->localizedText(
            Arr::get($attributes, 'titles'),
            [$language === 'ko' ? 'ko_kr' : 'ja_jp'],
            false,
        );

        return $originalTitle !== null && $originalTitle !== $displayTitle ? $originalTitle : null;
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

    private function publicationYear(mixed $date): ?int
    {
        if (! is_string($date) || preg_match('/^(\d{4})-/', $date, $matches) !== 1) {
            return null;
        }

        $year = (int) $matches[1];

        return $year > 0 ? $year : null;
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
