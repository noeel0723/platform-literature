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

        $creatorEnrichmentLimit = max(
            0,
            (int) config('services.comic_vine.creator_enrichment_limit', 4),
        );

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item)
                && Arr::get($item, 'resource_type') === 'volume')
            ->values()
            ->map(function (mixed $item, int $position) use ($apiKey, $creatorEnrichmentLimit): ?NormalizedLiterature {
                $creators = $position < $creatorEnrichmentLimit
                    ? $this->creators($item, $apiKey)
                    : [];

                return $this->normalize($item, $creators);
            })
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
                        'first_issue',
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

    /**
     * @param  list<NormalizedAuthor>  $creators
     */
    private function normalize(mixed $item, array $creators = []): ?NormalizedLiterature
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
            authors: array_values(array_map(
                fn (NormalizedAuthor $creator): string => $creator->name,
                $creators,
            )),
            categories: [],
            publicationYear: $this->publicationYear(Arr::get($item, 'start_year')),
            tagline: $this->cleanText(Arr::get($item, 'deck')),
            synopsis: $this->cleanText(Arr::get($item, 'description')),
            publisher: $this->cleanText(Arr::get($item, 'publisher.name')),
            language: null,
            format: 'Western Comic',
            identifier: "COMICVINE:4050-{$externalId}",
            coverUrl: $coverUrl,
            authorDetails: $creators,
        );
    }

    /**
     * Comic Vine volume search results do not contain creator credits. The first
     * issue is a stable, inexpensive representative from which writers and
     * artists can be discovered without fetching every issue in a volume.
     *
     * @return list<NormalizedAuthor>
     */
    private function creators(mixed $volume, string $apiKey): array
    {
        if (! is_array($volume)) {
            return [];
        }

        $issueId = trim((string) Arr::get($volume, 'first_issue.id', ''));

        if ($issueId === '') {
            return [];
        }

        $cacheKey = 'literature-source:comic-vine:issue-creators:'.$issueId;

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(max(1, (int) config('services.comic_vine.cache_minutes', 30))),
            fn (): array => $this->requestCreators($issueId, $apiKey),
        );
    }

    /** @return list<NormalizedAuthor> */
    private function requestCreators(string $issueId, string $apiKey): array
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('services.comic_vine.base_url'), '/'))
                ->acceptJson()
                ->withUserAgent((string) config('services.comic_vine.user_agent'))
                ->connectTimeout((int) config('services.comic_vine.connect_timeout', 3))
                ->timeout((int) config('services.comic_vine.timeout', 12))
                ->get("/issue/4000-{$issueId}/", [
                    'api_key' => $apiKey,
                    'format' => 'json',
                    'field_list' => 'id,person_credits',
                ]);
        } catch (ConnectionException) {
            return [];
        }

        if ($response->failed() || (int) $response->json('status_code', 0) !== 1) {
            return [];
        }

        $credits = $response->json('results.person_credits');

        if (! is_array($credits)) {
            return [];
        }

        return collect($credits)
            ->filter(fn (mixed $credit): bool => is_array($credit)
                && $this->isPrimaryCreatorRole(Arr::get($credit, 'role')))
            ->map(function (array $credit): ?NormalizedAuthor {
                $name = $this->cleanText(Arr::get($credit, 'person.name'));
                $externalId = trim((string) Arr::get($credit, 'person.id', ''));

                if ($name === null) {
                    return null;
                }

                return new NormalizedAuthor(
                    name: $name,
                    sourceUrl: $this->cleanText(
                        Arr::get($credit, 'person.site_detail_url')
                            ?? Arr::get($credit, 'person.api_detail_url'),
                    ),
                    externalId: $externalId === '' ? null : $externalId,
                );
            })
            ->filter()
            ->unique(fn (NormalizedAuthor $author): string => $author->externalId ?? Str::lower($author->name))
            ->values()
            ->all();
    }

    private function isPrimaryCreatorRole(mixed $role): bool
    {
        if (! is_string($role)) {
            return false;
        }

        return preg_match('/\b(writer|artist|pencill?er|script|story|plot|creator)\b/i', $role) === 1;
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
