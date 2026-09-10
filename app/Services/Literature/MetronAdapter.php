<?php

namespace App\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class MetronAdapter
{
    /** @return Collection<int, NormalizedLiterature> */
    public function search(string $query, int $limit = 6): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        if (! $this->isConfigured()) {
            throw new LiteratureSourceUnavailable(
                'Metron',
                'Metron credentials are not configured.',
            );
        }

        $normalizedLimit = max(1, min($limit, 100));
        $cacheKey = 'literature-source:metron:'.hash(
            'sha256',
            Str::lower($query).":{$normalizedLimit}",
        );

        $items = Cache::remember(
            $cacheKey,
            now()->addMinutes(max(1, (int) config('services.metron.cache_minutes', 30))),
            fn (): array => $this->requestAndEnrich($query, $normalizedLimit),
        );

        return collect($items)
            ->map(fn (mixed $item): ?NormalizedLiterature => $this->normalize($item))
            ->filter()
            ->values();
    }

    public function isConfigured(): bool
    {
        return filled(config('services.metron.token'))
            || (filled(config('services.metron.username')) && filled(config('services.metron.password')));
    }

    /** @return list<array<string, mixed>> */
    private function requestAndEnrich(string $query, int $limit): array
    {
        $payload = $this->request('series/', ['q' => $query]);
        $results = Arr::get($payload, 'results');

        if (! is_array($results)) {
            return [];
        }

        $detailLimit = max(0, (int) config('services.metron.detail_enrichment_limit', 4));

        return collect($results)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->take($limit)
            ->values()
            ->map(function (array $item, int $position) use ($detailLimit): array {
                if ($position >= $detailLimit) {
                    return $item;
                }

                return $this->enrich($item);
            })
            ->all();
    }

    /** @param array<string, mixed> $series */
    private function enrich(array $series): array
    {
        $seriesId = trim((string) Arr::get($series, 'id', ''));

        if ($seriesId === '') {
            return $series;
        }

        try {
            $detail = $this->request("series/{$seriesId}/");
            $issueList = $this->request("series/{$seriesId}/issue_list/");
            $firstIssueId = trim((string) (
                Arr::get($issueList, 'results.0.id')
                    ?? Arr::get($issueList, '0.id')
                    ?? ''
            ));
            $issue = $firstIssueId === '' ? [] : $this->request("issue/{$firstIssueId}/");

            return array_replace($series, $detail, ['_representative_issue' => $issue]);
        } catch (LiteratureSourceUnavailable) {
            // A partial result remains useful when optional enrichment fails.
            return $series;
        }
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function request(string $path, array $query = []): array
    {
        try {
            $response = $this->client()->get(ltrim($path, '/'), $query);
        } catch (ConnectionException $exception) {
            throw new LiteratureSourceUnavailable(
                'Metron',
                'Metron could not be reached.',
                $exception,
            );
        }

        if ($response->status() === 429) {
            throw new LiteratureSourceUnavailable('Metron', 'Metron rate limit was reached.');
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new LiteratureSourceUnavailable(
                'Metron',
                'Metron rejected the configured credentials.',
            );
        }

        if ($response->failed()) {
            throw new LiteratureSourceUnavailable(
                'Metron',
                "Metron returned HTTP {$response->status()}.",
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new LiteratureSourceUnavailable('Metron', 'Metron returned an invalid response.');
        }

        return $payload;
    }

    private function client(): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('services.metron.base_url'), '/').'/')
            ->acceptJson()
            ->withUserAgent((string) config('services.metron.user_agent'))
            ->connectTimeout((int) config('services.metron.connect_timeout', 3))
            ->timeout((int) config('services.metron.timeout', 12));
        $token = trim((string) config('services.metron.token'));

        if ($token !== '') {
            return $request->withToken($token);
        }

        return $request->withBasicAuth(
            (string) config('services.metron.username'),
            (string) config('services.metron.password'),
        );
    }

    private function normalize(mixed $item): ?NormalizedLiterature
    {
        if (! is_array($item)) {
            return null;
        }

        $externalId = trim((string) Arr::get($item, 'id', ''));
        $title = $this->seriesTitle(Arr::get($item, 'name') ?? Arr::get($item, 'series'));

        if ($externalId === '' || $title === null) {
            return null;
        }

        $creators = $this->creators(Arr::get($item, '_representative_issue.credits', []));
        $comicVineId = trim((string) Arr::get($item, 'cv_id', ''));
        $resourceUrl = $this->cleanText(Arr::get($item, 'resource_url'));

        return new NormalizedLiterature(
            externalId: $externalId,
            title: $title,
            type: 'western-comic',
            authors: array_values(array_map(
                fn (NormalizedAuthor $creator): string => $creator->name,
                $creators,
            )),
            categories: $this->names(Arr::get($item, 'genres', [])),
            publicationYear: $this->publicationYear(
                Arr::get($item, 'year_began') ?? Arr::get($item, 'year'),
            ),
            tagline: null,
            synopsis: $this->cleanText(Arr::get($item, 'desc') ?? Arr::get($item, 'description')),
            publisher: $this->nestedName(Arr::get($item, 'publisher')),
            language: $this->nestedName(Arr::get($item, 'language')),
            format: $this->nestedName(Arr::get($item, 'series_type')) ?? 'Western Comic',
            identifier: $comicVineId !== ''
                ? "COMICVINE:4050-{$comicVineId}"
                : "METRON:{$externalId}",
            coverUrl: $this->coverUrl(Arr::get($item, '_representative_issue.image')),
            synopsisSourceName: $resourceUrl === null ? null : 'Metron',
            synopsisSourceUrl: $resourceUrl,
            authorDetails: $creators,
        );
    }

    /** @return list<NormalizedAuthor> */
    private function creators(mixed $credits): array
    {
        if (! is_array($credits)) {
            return [];
        }

        return collect($credits)
            ->filter(fn (mixed $credit): bool => is_array($credit) && $this->hasPrimaryRole($credit))
            ->map(function (array $credit): ?NormalizedAuthor {
                $creator = Arr::get($credit, 'creator', $credit);

                if (! is_array($creator)) {
                    return null;
                }

                $name = $this->cleanText(Arr::get($creator, 'name'));

                if ($name === null) {
                    return null;
                }

                $externalId = trim((string) Arr::get($creator, 'id', ''));

                return new NormalizedAuthor(
                    name: $name,
                    imageUrl: $this->coverUrl(Arr::get($creator, 'image')),
                    biography: $this->cleanText(Arr::get($creator, 'desc')),
                    sourceUrl: $this->cleanText(Arr::get($creator, 'resource_url')),
                    externalId: $externalId === '' ? null : $externalId,
                );
            })
            ->filter()
            ->unique(fn (NormalizedAuthor $author): string => $author->externalId ?? Str::lower($author->name))
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $credit */
    private function hasPrimaryRole(array $credit): bool
    {
        $roles = Arr::get($credit, 'roles', Arr::get($credit, 'role', []));
        $roles = is_array($roles) ? $roles : [$roles];
        $roleNames = collect($roles)
            ->map(fn (mixed $role): ?string => is_array($role)
                ? $this->cleanText(Arr::get($role, 'name'))
                : $this->cleanText($role))
            ->filter()
            ->join(' ');

        return preg_match('/\b(writer|artist|pencill?er|script|story|plot|creator)\b/i', $roleNames) === 1;
    }

    /** @return list<string> */
    private function names(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->map(fn (mixed $value): ?string => $this->nestedName($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function nestedName(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = Arr::get($value, 'name');
        }

        return $this->cleanText($value);
    }

    private function seriesTitle(mixed $value): ?string
    {
        $title = $this->nestedName($value);

        if ($title === null) {
            return null;
        }

        return trim((string) preg_replace('/\s+\(\d{4}\)$/', '', $title));
    }

    private function coverUrl(mixed $image): ?string
    {
        if (is_array($image)) {
            $image = Arr::get($image, 'original_url')
                ?? Arr::get($image, 'large_url')
                ?? Arr::get($image, 'medium_url')
                ?? Arr::get($image, 'small_url')
                ?? Arr::get($image, 'url');
        }

        $url = $this->cleanText($image);

        return $url === null ? null : (preg_replace('#^http://#', 'https://', $url) ?? $url);
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
