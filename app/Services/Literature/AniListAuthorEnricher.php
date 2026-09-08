<?php

namespace App\Services\Literature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class AniListAuthorEnricher
{
    public function find(string $name): ?NormalizedAuthor
    {
        $name = Str::squish($name);

        if ($name === '') {
            return null;
        }

        $cacheKey = 'anilist:author:v1:'.sha1(Str::lower($name));
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return new NormalizedAuthor(...$cached);
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout((int) config('services.anilist.connect_timeout', 3))
                ->timeout((int) config('services.anilist.timeout', 12))
                ->post((string) config('services.anilist.base_url'), [
                    'query' => $this->searchQuery(),
                    'variables' => ['search' => $name],
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed() || filled($response->json('errors'))) {
            return null;
        }

        $candidate = collect($response->json('data.Page.staff'))
            ->filter(fn (mixed $staff): bool => $this->matches($staff, $name))
            ->first();

        if (! is_array($candidate)) {
            return null;
        }

        $author = $this->normalize($candidate);

        if ($author === null || $author->imageUrl === null) {
            return null;
        }

        Cache::put($cacheKey, [
            'name' => $author->name,
            'imageUrl' => $author->imageUrl,
            'biography' => $author->biography,
            'sourceUrl' => $author->sourceUrl,
        ], now()->addDays((int) config('services.anilist.author_cache_days', 30)));

        return $author;
    }

    private function matches(mixed $staff, string $name): bool
    {
        if (! is_array($staff)) {
            return false;
        }

        $candidateNames = collect([
            Arr::get($staff, 'name.full'),
            Arr::get($staff, 'name.native'),
            Arr::get($staff, 'name.userPreferred'),
            ...((array) Arr::get($staff, 'name.alternative', [])),
        ])->filter(fn (mixed $candidateName): bool => is_string($candidateName));

        return $candidateNames->contains(
            fn (string $candidateName): bool => $this->normalizeName($candidateName) === $this->normalizeName($name),
        );
    }

    private function normalize(array $staff): ?NormalizedAuthor
    {
        $name = $this->cleanText(
            Arr::get($staff, 'name.full')
                ?? Arr::get($staff, 'name.userPreferred')
                ?? Arr::get($staff, 'name.native'),
        );

        if ($name === null) {
            return null;
        }

        return new NormalizedAuthor(
            name: $name,
            imageUrl: $this->cleanUrl(
                Arr::get($staff, 'image.large')
                    ?? Arr::get($staff, 'image.medium'),
            ),
            biography: $this->cleanText(Arr::get($staff, 'description')),
            sourceUrl: $this->cleanUrl(Arr::get($staff, 'siteUrl')),
        );
    }

    private function normalizeName(string $name): string
    {
        return Str::of($name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->toString();
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
            query SearchAuthor($search: String!) {
              Page(page: 1, perPage: 5) {
                staff(search: $search, sort: SEARCH_MATCH) {
                  id
                  name {
                    full
                    native
                    userPreferred
                    alternative
                  }
                  image {
                    large
                    medium
                  }
                  description(asHtml: false)
                  primaryOccupations
                  siteUrl
                }
              }
            }
            GRAPHQL;
    }
}
