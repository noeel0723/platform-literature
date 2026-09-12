<?php

namespace App\Services\Literature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class MangaUpdatesCreatorEnricher
{
    /**
     * @param  array<string, array{title: string, type: string}>  $works
     * @return array<string, list<NormalizedAuthor>>
     */
    public function forWorks(array $works): array
    {
        $baseUrl = rtrim((string) config('services.mangaupdates.base_url'), '/');

        if ($baseUrl === '' || $works === []) {
            return [];
        }

        $works = collect($works)
            ->mapWithKeys(function (mixed $work, mixed $externalId): array {
                $title = $this->cleanText(is_array($work) ? ($work['title'] ?? null) : null);
                $type = Str::lower((string) (is_array($work) ? ($work['type'] ?? '') : ''));

                if ($title === null || ! in_array($type, ['manga', 'manhwa'], true)) {
                    return [];
                }

                return [(string) $externalId => ['title' => $title, 'type' => $type]];
            })
            ->all();
        $records = [];
        $pending = [];

        foreach ($works as $externalId => $work) {
            $cached = Cache::get($this->cacheKey($work['title'], $work['type']));

            if (is_array($cached)) {
                $records[$externalId] = $cached;
            } else {
                $pending[$externalId] = $work;
            }
        }

        if ($pending !== []) {
            $this->resolve($baseUrl, $pending, $records);
        }

        return collect($records)
            ->map(fn (array $creators): array => collect($creators)
                ->map(fn (array $creator): NormalizedAuthor => new NormalizedAuthor(
                    name: $creator['name'],
                    sourceUrl: $creator['source_url'] ?? null,
                ))
                ->all())
            ->filter(fn (array $creators): bool => $creators !== [])
            ->all();
    }

    /**
     * @param  array<string, array{title: string, type: string}>  $works
     * @param  array<string, list<array{name: string, source_url: string|null}>>  $records
     */
    private function resolve(string $baseUrl, array $works, array &$records): void
    {
        try {
            $searchResponses = Http::pool(fn (Pool $pool): array => collect($works)
                ->map(fn (array $work, string $externalId) => $this->request($pool, "work-{$externalId}")
                    ->post("{$baseUrl}/series/search", [
                        'search' => $work['title'],
                        'page' => 1,
                        'perpage' => 5,
                    ]))
                ->values()
                ->all());
        } catch (ConnectionException) {
            return;
        }

        $matches = [];

        foreach ($works as $externalId => $work) {
            $response = $searchResponses["work-{$externalId}"] ?? null;

            if (! $response instanceof Response || ! $response->successful()) {
                continue;
            }

            $match = collect($response->json('results'))
                ->first(fn (mixed $result): bool => $this->matches($result, $work['title'], $work['type']));
            $seriesId = is_array($match) ? $this->cleanText(Arr::get($match, 'record.series_id')) : null;

            if ($seriesId === null) {
                $this->store($work, []);
                $records[$externalId] = [];

                continue;
            }

            $matches[$externalId] = ['series_id' => $seriesId, ...$work];
        }

        if ($matches === []) {
            return;
        }

        try {
            $detailResponses = Http::pool(fn (Pool $pool): array => collect($matches)
                ->map(fn (array $match, string $externalId) => $this->request($pool, "work-{$externalId}")
                    ->get("{$baseUrl}/series/{$match['series_id']}"))
                ->values()
                ->all());
        } catch (ConnectionException) {
            return;
        }

        foreach ($matches as $externalId => $match) {
            $response = $detailResponses["work-{$externalId}"] ?? null;

            if (! $response instanceof Response || ! $response->successful()) {
                continue;
            }

            $creators = $this->creators($response->json('authors'));
            $records[$externalId] = $creators;
            $this->store($match, $creators);
        }
    }

    private function request(Pool $pool, string $key): mixed
    {
        return $pool->as($key)
            ->withHeaders(['User-Agent' => (string) config('services.mangaupdates.user_agent')])
            ->acceptJson()
            ->connectTimeout((int) config('services.mangaupdates.connect_timeout', 3))
            ->timeout((int) config('services.mangaupdates.timeout', 12));
    }

    private function matches(mixed $result, string $title, string $type): bool
    {
        if (! is_array($result)) {
            return false;
        }

        $matchedTitle = $this->cleanText(Arr::get($result, 'record.title'))
            ?? $this->cleanText(Arr::get($result, 'hit_title'));
        $matchedType = Str::lower((string) Arr::get($result, 'record.type'));

        return $this->normalizedTitle($matchedTitle) === $this->normalizedTitle($title)
            && $matchedType === $type;
    }

    /** @return list<array{name: string, source_url: string|null}> */
    private function creators(mixed $authors): array
    {
        if (! is_array($authors)) {
            return [];
        }

        return collect($authors)
            ->filter(fn (mixed $author): bool => is_array($author))
            ->sortBy(fn (array $author): int => Str::lower((string) ($author['type'] ?? '')) === 'author' ? 0 : 1)
            ->map(function (array $author): ?array {
                $name = $this->cleanText($author['name'] ?? null);
                $sourceUrl = $this->cleanUrl($author['url'] ?? null);

                return $name === null ? null : ['name' => $name, 'source_url' => $sourceUrl];
            })
            ->filter()
            ->unique(fn (array $author): string => Str::lower($author['name']))
            ->values()
            ->all();
    }

    /** @param array{title: string, type: string} $work */
    private function store(array $work, array $creators): void
    {
        Cache::put(
            $this->cacheKey($work['title'], $work['type']),
            $creators,
            now()->addDays(max(1, (int) config('services.mangaupdates.cache_days', 30))),
        );
    }

    private function cacheKey(string $title, string $type): string
    {
        return 'mangaupdates-creators:v2:'.sha1($type.'|'.$this->normalizedTitle($title));
    }

    private function normalizedTitle(?string $title): string
    {
        return Str::of((string) $title)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->toString();
    }

    private function cleanText(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $cleaned = Str::squish(html_entity_decode(strip_tags((string) $value)));

        return $cleaned === '' ? null : $cleaned;
    }

    private function cleanUrl(mixed $value): ?string
    {
        $url = $this->cleanText($value);

        return $url !== null && filter_var($url, FILTER_VALIDATE_URL) !== false
            ? (preg_replace('#^http://#', 'https://', $url) ?? $url)
            : null;
    }
}
