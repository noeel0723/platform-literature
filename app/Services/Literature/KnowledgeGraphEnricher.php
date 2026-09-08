<?php

namespace App\Services\Literature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class KnowledgeGraphEnricher
{
    /** @param list<string> $authors */
    public function find(string $title, array $authors = []): ?KnowledgeGraphEntity
    {
        $title = Str::squish($title);
        $apiKey = trim((string) config('services.knowledge_graph.key'));

        if ($title === '' || $apiKey === '') {
            return null;
        }

        $language = $this->normalizedLanguage((string) config('services.knowledge_graph.language', 'en'));
        $cacheKey = 'knowledge-graph:'.sha1($language.'|'.$title.'|'.implode('|', $authors));
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return new KnowledgeGraphEntity(...$cached);
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout((int) config('services.knowledge_graph.connect_timeout', 3))
                ->timeout((int) config('services.knowledge_graph.timeout', 8))
                ->get((string) config('services.knowledge_graph.base_url'), [
                    'query' => $this->searchQuery($title, $authors),
                    'languages' => $language,
                    'limit' => max(1, min((int) config('services.knowledge_graph.candidate_limit', 5), 20)),
                    'key' => $apiKey,
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $candidate = collect($response->json('itemListElement'))
            ->filter(fn (mixed $item): bool => $this->isMatchingLiterature($item, $title))
            ->sortByDesc(fn (array $item): float => $this->candidateScore($item, $authors))
            ->first();

        if (! is_array($candidate)) {
            return null;
        }

        $entity = $this->toEntity($candidate);

        if ($entity === null) {
            return null;
        }

        Cache::put($cacheKey, [
            'id' => $entity->id,
            'name' => $entity->name,
            'types' => $entity->types,
            'description' => $entity->description,
            'detailedDescription' => $entity->detailedDescription,
            'sourceUrl' => $entity->sourceUrl,
            'officialUrl' => $entity->officialUrl,
            'score' => $entity->score,
            'imageUrl' => $entity->imageUrl,
            'imageLicenseUrl' => $entity->imageLicenseUrl,
        ], now()->addDays((int) config('services.knowledge_graph.cache_days', 30)));

        return $entity;
    }

    public function findAuthor(string $name): ?KnowledgeGraphEntity
    {
        $name = Str::squish($name);
        $apiKey = trim((string) config('services.knowledge_graph.key'));

        if ($name === '' || $apiKey === '') {
            return null;
        }

        $language = $this->normalizedLanguage((string) config('services.knowledge_graph.language', 'en'));
        $cacheKey = 'knowledge-graph:author:'.sha1($language.'|'.$name);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return new KnowledgeGraphEntity(...$cached);
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout((int) config('services.knowledge_graph.connect_timeout', 3))
                ->timeout((int) config('services.knowledge_graph.timeout', 8))
                ->get((string) config('services.knowledge_graph.base_url'), [
                    'query' => "{$name} author",
                    'languages' => $language,
                    'types' => 'Person',
                    'limit' => max(1, min((int) config('services.knowledge_graph.candidate_limit', 5), 20)),
                    'key' => $apiKey,
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $candidate = collect($response->json('itemListElement'))
            ->filter(fn (mixed $item): bool => $this->isMatchingAuthor($item, $name))
            ->sortByDesc(fn (array $item): float => (float) Arr::get($item, 'resultScore', 0))
            ->first();

        if (! is_array($candidate)) {
            return null;
        }

        $entity = $this->toEntity($candidate);

        if ($entity === null) {
            return null;
        }

        Cache::put($cacheKey, [
            'id' => $entity->id,
            'name' => $entity->name,
            'types' => $entity->types,
            'description' => $entity->description,
            'detailedDescription' => $entity->detailedDescription,
            'sourceUrl' => $entity->sourceUrl,
            'officialUrl' => $entity->officialUrl,
            'score' => $entity->score,
            'imageUrl' => $entity->imageUrl,
            'imageLicenseUrl' => $entity->imageLicenseUrl,
        ], now()->addDays((int) config('services.knowledge_graph.cache_days', 30)));

        return $entity;
    }

    /** @param list<string> $authors */
    private function searchQuery(string $title, array $authors): string
    {
        $primaryAuthor = collect($authors)
            ->map(fn (mixed $author): string => Str::squish((string) $author))
            ->first(fn (string $author): bool => $author !== '');

        return $primaryAuthor === null ? $title : "{$title} {$primaryAuthor}";
    }

    private function isMatchingLiterature(mixed $item, string $title): bool
    {
        if (! is_array($item)) {
            return false;
        }

        $name = $this->cleanText(Arr::get($item, 'result.name'));

        if (! $this->sameTitle($name, $title)) {
            return false;
        }

        $types = $this->stringList(Arr::get($item, 'result.@type'));
        $description = Str::lower(implode(' ', array_filter([
            $this->cleanText(Arr::get($item, 'result.description')),
            $this->cleanText(Arr::get($item, 'result.detailedDescription.articleBody')),
        ])));
        $literatureTypes = ['Book', 'BookSeries', 'ComicStory', 'CreativeWork', 'WrittenWork'];
        $literatureTerms = '/\b(book|novel|novella|literary work|book series|comic|manga|manhwa|light novel|short story|poem|play)\b/u';

        return array_intersect($types, $literatureTypes) !== []
            || preg_match($literatureTerms, $description) === 1;
    }

    private function isMatchingAuthor(mixed $item, string $name): bool
    {
        if (! is_array($item) || ! $this->sameTitle($this->cleanText(Arr::get($item, 'result.name')), $name)) {
            return false;
        }

        $types = $this->stringList(Arr::get($item, 'result.@type'));
        $description = Str::lower(implode(' ', array_filter([
            $this->cleanText(Arr::get($item, 'result.description')),
            $this->cleanText(Arr::get($item, 'result.detailedDescription.articleBody')),
        ])));

        return in_array('Person', $types, true)
            && (preg_match('/\b(author|writer|novelist|poet|mangaka|manga artist|comic artist|screenwriter|illustrator|creator)\b/u', $description) === 1
                || $description === '');
    }

    /** @param list<string> $authors */
    private function candidateScore(array $item, array $authors): float
    {
        $score = (float) Arr::get($item, 'resultScore', 0);
        $description = Str::lower(implode(' ', array_filter([
            $this->cleanText(Arr::get($item, 'result.description')),
            $this->cleanText(Arr::get($item, 'result.detailedDescription.articleBody')),
        ])));
        $mentionsAuthor = collect($authors)
            ->map(fn (string $author): string => Str::lower(Str::squish($author)))
            ->contains(fn (string $author): bool => $author !== '' && Str::contains($description, $author));

        return $score + ($mentionsAuthor ? 1_000_000 : 0);
    }

    private function toEntity(array $item): ?KnowledgeGraphEntity
    {
        $id = $this->cleanText(Arr::get($item, 'result.@id'));
        $name = $this->cleanText(Arr::get($item, 'result.name'));

        if ($id === null || $name === null) {
            return null;
        }

        return new KnowledgeGraphEntity(
            id: $id,
            name: $name,
            types: $this->stringList(Arr::get($item, 'result.@type')),
            description: $this->cleanText(Arr::get($item, 'result.description')),
            detailedDescription: $this->cleanText(Arr::get($item, 'result.detailedDescription.articleBody')),
            sourceUrl: $this->cleanText(Arr::get($item, 'result.detailedDescription.url')),
            officialUrl: $this->cleanText(Arr::get($item, 'result.url')),
            score: (float) Arr::get($item, 'resultScore', 0),
            imageUrl: $this->cleanUrl(Arr::get($item, 'result.image.contentUrl')),
            imageLicenseUrl: $this->cleanUrl(Arr::get($item, 'result.image.license')),
        );
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

    private function normalizedLanguage(string $language): string
    {
        $language = Str::lower(trim($language));

        return preg_match('/^[a-z]{2,3}$/', $language) === 1 ? $language : 'en';
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

    private function cleanUrl(mixed $value): ?string
    {
        $url = $this->cleanText($value);

        if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        if (! str_starts_with($url, 'https://') && ! str_starts_with($url, 'http://')) {
            return null;
        }

        return preg_replace('#^http://#', 'https://', $url) ?? $url;
    }
}
