<?php

namespace App\Services\Literature;

use App\Models\ApiSource;
use App\Models\Author;
use App\Models\Category;
use App\Models\Literature;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CatalogSyncService
{
    public function __construct(
        private GoogleBooksAdapter $googleBooks,
        private AniListAdapter $aniList,
    ) {}

    public function syncGoogleBooks(string $query): int
    {
        $items = $this->googleBooks->search(
            $query,
            (int) config('services.google_books.max_results', 6),
        );

        return $this->sync(
            sourceKey: 'google-books',
            sourceName: 'Google Books',
            baseUrl: (string) config('services.google_books.base_url'),
            supportedTypes: ['book'],
            items: $items,
        );
    }

    public function syncAniList(string $query, string $literatureType): int
    {
        if (! in_array($literatureType, ['all', 'manga', 'light-novel'], true)) {
            throw new InvalidArgumentException('AniList sync only supports all, manga, and light-novel types.');
        }

        $items = $this->aniList->search(
            $query,
            $literatureType,
            (int) config('services.anilist.max_results', 6),
        );

        return $this->sync(
            sourceKey: 'anilist',
            sourceName: 'AniList',
            baseUrl: (string) config('services.anilist.base_url'),
            supportedTypes: ['manga', 'light-novel'],
            items: $items,
        );
    }

    /**
     * @param  list<string>  $supportedTypes
     * @param  Collection<int, NormalizedLiterature>  $items
     */
    private function sync(
        string $sourceKey,
        string $sourceName,
        string $baseUrl,
        array $supportedTypes,
        Collection $items,
    ): int {
        if ($items->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use (
            $sourceKey,
            $sourceName,
            $baseUrl,
            $supportedTypes,
            $items,
        ): int {
            $source = ApiSource::query()->updateOrCreate(
                ['key' => $sourceKey],
                [
                    'name' => $sourceName,
                    'base_url' => rtrim($baseUrl, '/'),
                    'supported_types' => $supportedTypes,
                    'is_active' => true,
                ],
            );

            foreach ($items as $item) {
                $this->persist($source, $item);
            }

            return $items->count();
        });
    }

    private function persist(ApiSource $source, NormalizedLiterature $item): Literature
    {
        $literature = Literature::query()
            ->whereBelongsTo($source)
            ->where('external_id', $item->externalId)
            ->first();

        if ($literature === null && $item->identifier !== null) {
            $literature = Literature::query()
                ->whereBelongsTo($source)
                ->whereIn('identifier', [$item->identifier, "ISBN {$item->identifier}"])
                ->first();
        }

        if ($literature === null && $item->authors !== []) {
            $literature = Literature::query()
                ->whereBelongsTo($source)
                ->where('title', $item->title)
                ->when(
                    $item->publicationYear !== null,
                    fn ($query) => $query->where('publication_year', $item->publicationYear),
                )
                ->whereHas('authors', fn ($query) => $query->where('slug', Str::slug($item->authors[0])))
                ->first();
        }

        $literature ??= new Literature;

        $literature->fill([
            'api_source_id' => $source->id,
            'external_id' => $item->externalId,
            'slug' => $literature->exists ? $literature->slug : $this->uniqueSlug($source, $item),
            'title' => $item->title,
            'type' => $item->type,
            'publication_year' => $item->publicationYear ?? $literature->publication_year,
            'tagline' => $item->tagline ?? $literature->tagline,
            'synopsis' => $item->synopsis ?? $literature->synopsis,
            'publisher' => $item->publisher ?? $literature->publisher,
            'language' => $item->language ?? $literature->language,
            'format' => $item->format ?? $literature->format,
            'identifier' => $item->identifier ?? $literature->identifier,
            'cover_url' => $item->coverUrl ?? $literature->cover_url,
            'theme' => $literature->theme ?? 'cream',
        ]);
        $literature->save();

        $this->syncAuthors($literature, $item->authors);
        $this->syncCategories($literature, $item->categories);

        return $literature;
    }

    /** @param list<string> $authorNames */
    private function syncAuthors(Literature $literature, array $authorNames): void
    {
        if ($authorNames === []) {
            return;
        }

        $authorLinks = [];

        foreach ($authorNames as $position => $authorName) {
            $author = Author::query()->firstOrCreate(
                ['slug' => Str::slug($authorName)],
                ['name' => $authorName],
            );

            $authorLinks[$author->id] = [
                'role' => 'author',
                'position' => $position,
            ];
        }

        $literature->authors()->sync($authorLinks);
    }

    /** @param list<string> $categoryNames */
    private function syncCategories(Literature $literature, array $categoryNames): void
    {
        if ($categoryNames === []) {
            return;
        }

        $categoryIds = collect($categoryNames)
            ->map(fn (string $categoryName): int => Category::query()->firstOrCreate(
                ['slug' => Str::slug($categoryName)],
                ['name' => $categoryName],
            )->id)
            ->all();

        $literature->categories()->sync($categoryIds);
    }

    private function uniqueSlug(ApiSource $source, NormalizedLiterature $item): string
    {
        $baseSlug = Str::slug($item->title);

        if (! Literature::query()->where('slug', $baseSlug)->exists()) {
            return $baseSlug;
        }

        return Str::limit($baseSlug, 180, '')
            .'-'.$source->key.'-'
            .Str::slug($item->externalId);
    }
}
