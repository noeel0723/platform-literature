<?php

namespace App\Services\Literature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class WorkMetadataEnricher
{
    /** @param list<string> $authors */
    public function find(string $title, array $authors, ?string $language, bool $needsSynopsis = true): WorkMetadata
    {
        $title = Str::squish($title);

        if ($title === '') {
            return new WorkMetadata;
        }

        $language = $this->normalizedLanguage($language);
        $cacheKey = 'work-metadata:'.sha1($language.'|'.$title.'|'.implode('|', $authors));

        $cached = Cache::get($cacheKey);

        if (is_array($cached) && (! $needsSynopsis || filled($cached['synopsis'] ?? null))) {
            return new WorkMetadata(...$cached);
        }

        $metadata = $this->resolve($title, $language);

        if ($this->hasMetadata($metadata)) {
            Cache::put($cacheKey, [
                'originalTitle' => $metadata->originalTitle,
                'tagline' => $metadata->tagline,
                'synopsis' => $metadata->synopsis,
                'synopsisSourceName' => $metadata->synopsisSourceName,
                'synopsisSourceUrl' => $metadata->synopsisSourceUrl,
            ], now()->addDays((int) config('services.work_metadata.cache_days', 30)));
        }

        return $metadata;
    }

    private function resolve(string $title, string $language): WorkMetadata
    {
        try {
            $searchResponse = $this->client()->get((string) config('services.work_metadata.wikidata_url'), [
                'action' => 'wbsearchentities',
                'search' => $title,
                'language' => $language,
                'uselang' => 'en',
                'type' => 'item',
                'limit' => 5,
                'format' => 'json',
            ]);

            if ($searchResponse->failed()) {
                return new WorkMetadata;
            }

            $candidate = collect($searchResponse->json('search'))
                ->first(fn (mixed $item): bool => $this->isMatchingWork($item, $title));

            if (! is_array($candidate) || ! filled($candidate['id'] ?? null)) {
                return new WorkMetadata;
            }

            $entityResponse = $this->client()->get((string) config('services.work_metadata.wikidata_url'), [
                'action' => 'wbgetentities',
                'ids' => $candidate['id'],
                'props' => 'claims|sitelinks|descriptions',
                'languages' => "{$language}|en",
                'format' => 'json',
            ]);

            if ($entityResponse->failed()) {
                return new WorkMetadata;
            }

            $entity = $entityResponse->json("entities.{$candidate['id']}");

            if (! is_array($entity)) {
                return new WorkMetadata;
            }

            $originalTitle = $this->originalTitle($entity);
            $tagline = $this->cleanText(
                Arr::get($entity, "descriptions.{$language}.value")
                    ?? Arr::get($entity, 'descriptions.en.value'),
            );
            $wiki = $this->wikipediaSummary($entity, $language);

            return new WorkMetadata(
                originalTitle: $this->sameTitle($originalTitle, $title) ? null : $originalTitle,
                tagline: $tagline,
                synopsis: $wiki['synopsis'],
                synopsisSourceName: $wiki['source_name'],
                synopsisSourceUrl: $wiki['source_url'],
            );
        } catch (ConnectionException) {
            return new WorkMetadata;
        }
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => (string) config('services.work_metadata.user_agent'),
        ])->acceptJson()
            ->connectTimeout((int) config('services.work_metadata.connect_timeout', 3))
            ->timeout((int) config('services.work_metadata.timeout', 8));
    }

    private function isMatchingWork(mixed $item, string $title): bool
    {
        if (! is_array($item)) {
            return false;
        }

        $matchedTitle = $this->cleanText(Arr::get($item, 'match.text'));
        $description = Str::lower((string) Arr::get($item, 'description', ''));
        $workTerms = '/\b(novel|novella|literary work|book series|book (?:by|written|published)|comic book|manga|manhwa|short story|poem|play)\b/u';

        return $this->sameTitle($matchedTitle, $title)
            && preg_match($workTerms, $description) === 1;
    }

    private function originalTitle(array $entity): ?string
    {
        $claims = Arr::get($entity, 'claims.P1476');

        if (! is_array($claims)) {
            return null;
        }

        $preferred = collect($claims)->firstWhere('rank', 'preferred') ?? $claims[0] ?? null;

        return $this->cleanText(Arr::get($preferred, 'mainsnak.datavalue.value.text'));
    }

    /** @return array{synopsis: ?string, source_name: ?string, source_url: ?string} */
    private function wikipediaSummary(array $entity, string $language): array
    {
        $siteKey = Arr::has($entity, "sitelinks.{$language}wiki") ? "{$language}wiki" : 'enwiki';
        $pageTitle = $this->cleanText(Arr::get($entity, "sitelinks.{$siteKey}.title"));

        if ($pageTitle === null) {
            return ['synopsis' => null, 'source_name' => null, 'source_url' => null];
        }

        $wikiLanguage = $siteKey === 'enwiki' ? 'en' : $language;
        $endpoint = str_replace(
            ['{language}', '{title}'],
            [$wikiLanguage, rawurlencode(str_replace(' ', '_', $pageTitle))],
            (string) config('services.work_metadata.wikipedia_summary_url'),
        );

        try {
            $response = $this->client()->get($endpoint);
        } catch (ConnectionException) {
            return ['synopsis' => null, 'source_name' => null, 'source_url' => null];
        }

        if ($response->failed()) {
            return ['synopsis' => null, 'source_name' => null, 'source_url' => null];
        }

        $synopsis = $this->cleanText($response->json('extract'));
        $sourceUrl = $this->cleanText($response->json('content_urls.desktop.page'));

        if ($synopsis === null || $sourceUrl === null) {
            return ['synopsis' => null, 'source_name' => null, 'source_url' => null];
        }

        return [
            'synopsis' => Str::limit($synopsis, 1800),
            'source_name' => 'Wikipedia '.Str::upper($wikiLanguage),
            'source_url' => $sourceUrl,
        ];
    }

    private function normalizedLanguage(?string $language): string
    {
        $language = Str::lower(trim((string) $language));

        return preg_match('/^[a-z]{2,3}$/', $language) === 1 ? $language : 'en';
    }

    private function hasMetadata(WorkMetadata $metadata): bool
    {
        return $metadata->originalTitle !== null
            || $metadata->tagline !== null
            || $metadata->synopsis !== null;
    }

    private function sameTitle(?string $left, ?string $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }

        $normalize = fn (string $value): string => Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->toString();

        return $normalize($left) === $normalize($right);
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
