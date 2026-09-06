<?php

namespace App\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class ComicVineAdapter
{
    /** @return Collection<int, NormalizedLiterature> */
    public function search(string $query, int $limit = 6): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        $apiKey = trim((string) config('services.comic_vine.key'));

        if ($apiKey === '') {
            throw new LiteratureSourceUnavailable(
                'Comic Vine',
                'Comic Vine API key is not configured.',
            );
        }

        $normalizedLimit = max(1, min($limit, 100));
        $cacheKey = 'literature-source:comic-vine:'.hash(
            'sha256',
            Str::lower($query).":{$normalizedLimit}",
        );

        $items = Cache::remember(
            $cacheKey,
            now()->addMinutes(max(1, (int) config('services.comic_vine.cache_minutes', 30))),
            fn (): array => $this->request($query, $normalizedLimit, $apiKey),
        );

        return collect($items)
            ->map(fn (mixed $item): ?NormalizedLiterature => $this->normalize($item))
            ->filter()
            ->values();
    }

    /** @return list<mixed> */
    private function request(string $query, int $limit, string $apiKey): array
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('services.comic_vine.base_url'), '/'))
                ->acceptJson()
                ->withUserAgent((string) config('services.comic_vine.user_agent'))
                ->connectTimeout((int) config('services.comic_vine.connect_timeout', 3))
                ->timeout((int) config('services.comic_vine.timeout', 12))
                ->get('/search/', [
                    'api_key' => $apiKey,
                    'format' => 'json',
                    'resources' => 'volume',
                    'query' => $query,
                    'limit' => $limit,
                    'field_list' => implode(',', [
                        'id',
                        'name',
                        'deck',
                        'description',
                        'start_year',
                        'publisher',
                        'image',
                        'resource_type',
                    ]),
                ]);
        } catch (ConnectionException $exception) {
            throw new LiteratureSourceUnavailable(
                'Comic Vine',
                'Comic Vine could not be reached.',
                $exception,
            );
        }

        if ($response->status() === 429) {
            throw new LiteratureSourceUnavailable(
                'Comic Vine',
                'Comic Vine rate limit was reached.',
            );
        }

        if ($response->failed()) {
            throw new LiteratureSourceUnavailable(
                'Comic Vine',
                "Comic Vine returned HTTP {$response->status()}.",
            );
        }

        $statusCode = (int) $response->json('status_code', 0);

        if ($statusCode !== 1) {
            $message = $this->cleanText($response->json('error')) ?? 'Unknown API error';

            throw new LiteratureSourceUnavailable(
                'Comic Vine',
                "Comic Vine returned an API error: {$message}.",
            );
        }

        $items = $response->json('results');

        if (! is_array($items)) {
            return [];
        }

        return array_values($items);
    }

    private function normalize(mixed $item): ?NormalizedLiterature
    {
        if (! is_array($item) || Arr::get($item, 'resource_type') !== 'volume') {
            return null;
        }

        $externalId = trim((string) Arr::get($item, 'id', ''));
        $title = $this->cleanText(Arr::get($item, 'name'));

        if ($externalId === '' || $title === null) {
            return null;
        }

        $coverUrl = $this->cleanText(
            Arr::get($item, 'image.super_url')
                ?? Arr::get($item, 'image.original_url')
                ?? Arr::get($item, 'image.medium_url'),
        );

        if ($coverUrl !== null) {
            $coverUrl = preg_replace('#^http://#', 'https://', $coverUrl) ?? $coverUrl;
        }

        return new NormalizedLiterature(
            externalId: $externalId,
            title: $title,
            type: 'western-comic',
            authors: [],
            categories: [],
            publicationYear: $this->publicationYear(Arr::get($item, 'start_year')),
            tagline: $this->cleanText(Arr::get($item, 'deck')),
            synopsis: $this->cleanText(Arr::get($item, 'description')),
            publisher: $this->cleanText(Arr::get($item, 'publisher.name')),
            language: null,
            format: 'Western Comic',
            identifier: "COMICVINE:4050-{$externalId}",
            coverUrl: $coverUrl,
        );
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
