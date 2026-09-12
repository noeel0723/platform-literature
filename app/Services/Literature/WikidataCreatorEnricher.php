<?php

namespace App\Services\Literature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class WikidataCreatorEnricher
{
    /**
     * @param  list<string>  $externalIds
     * @return array<string, list<NormalizedAuthor>>
     */
    public function forKitsuIds(array $externalIds): array
    {
        $ids = collect($externalIds)
            ->map(fn (mixed $id): string => trim((string) $id))
            ->filter(fn (string $id): bool => preg_match('/^\d+$/', $id) === 1)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $endpoint = trim((string) config('services.work_metadata.wikidata_sparql_url'));

        if ($ids === [] || $endpoint === '') {
            return [];
        }

        $records = [];

        foreach (array_chunk($ids, 15) as $chunk) {
            $cacheKey = 'wikidata-creators:kitsu:v5:'.hash('sha256', implode('|', $chunk));
            $chunkRecords = Cache::get($cacheKey);

            if (! is_array($chunkRecords)) {
                $chunkRecords = $this->resolve($endpoint, $chunk);

                if ($chunkRecords === null) {
                    continue;
                }

                Cache::put(
                    $cacheKey,
                    $chunkRecords,
                    now()->addDays(max(1, (int) config('services.work_metadata.cache_days', 30))),
                );
            }

            foreach ($chunkRecords as $externalId => $creators) {
                $records[$externalId] = [
                    ...($records[$externalId] ?? []),
                    ...$creators,
                ];
            }
        }

        return collect($records)
            ->map(fn (array $creators): array => collect($creators)
                ->map(fn (array $creator): NormalizedAuthor => new NormalizedAuthor(
                    name: $creator['name'],
                    sourceUrl: $creator['source_url'],
                ))
                ->all())
            ->all();
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, list<array{name: string, source_url: string}>>|null
     */
    private function resolve(string $endpoint, array $ids): ?array
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/sparql-results+json',
                'User-Agent' => (string) config('services.work_metadata.user_agent'),
            ])->connectTimeout((int) config('services.work_metadata.connect_timeout', 3))
                ->timeout((int) config('services.work_metadata.timeout', 8))
                ->get($endpoint, [
                    'format' => 'json',
                    'query' => $this->query($ids),
                ]);
        } catch (ConnectionException) {
            return null;
        }

        $bindings = $response->successful()
            ? $response->json('results.bindings')
            : null;

        if (! is_array($bindings)) {
            return null;
        }

        return collect($bindings)
            ->filter(fn (mixed $binding): bool => is_array($binding))
            ->map(function (array $binding): ?array {
                $externalId = $this->cleanText(Arr::get($binding, 'externalId.value'));
                $name = $this->cleanText(Arr::get($binding, 'creatorLabel.value'));
                $creatorUrl = $this->cleanText(Arr::get($binding, 'creator.value'));
                $role = $this->cleanText(Arr::get($binding, 'role.value')) ?? 'artist';
                $entityId = $creatorUrl === null ? null : basename(parse_url($creatorUrl, PHP_URL_PATH) ?: '');

                if ($externalId === null || $name === null || $creatorUrl === null || $name === $entityId) {
                    return null;
                }

                return [
                    'external_id' => $externalId,
                    'name' => $name,
                    'source_url' => preg_replace('#^http://#', 'https://', $creatorUrl) ?? $creatorUrl,
                    'role' => $role,
                ];
            })
            ->filter()
            ->groupBy('external_id')
            ->map(fn ($creators): array => $creators
                ->sortBy(fn (array $creator): int => match ($creator['role']) {
                    'author' => 0,
                    'creator' => 1,
                    default => 2,
                })
                ->unique(fn (array $creator): string => Str::lower($creator['name']))
                ->map(fn (array $creator): array => [
                    'name' => $creator['name'],
                    'source_url' => $creator['source_url'],
                ])
                ->values()
                ->all())
            ->all();
    }

    /** @param list<string> $ids */
    private function query(array $ids): string
    {
        $values = collect($ids)
            ->map(fn (string $id): string => '"'.$id.'"')
            ->implode(' ');

        return <<<SPARQL
            SELECT DISTINCT ?externalId ?creator ?creatorLabel ?role WHERE {
              VALUES ?externalId { {$values} }
              ?work wdt:P11494 ?externalId.
              {
                ?work wdt:P50 ?creator.
                BIND("author" AS ?role)
              }
              UNION
              {
                ?work wdt:P110 ?creator.
                BIND("artist" AS ?role)
              }
              UNION
              {
                ?work wdt:P170 ?creator.
                BIND("creator" AS ?role)
              }
              SERVICE wikibase:label { bd:serviceParam wikibase:language "en,ko". }
            }
            SPARQL;
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
