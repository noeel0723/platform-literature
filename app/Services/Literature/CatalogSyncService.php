<?php

namespace App\Services\Literature;

use App\Models\ApiSource;
use App\Models\Author;
use App\Models\Category;
use App\Models\Literature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CatalogSyncService
{
    public function __construct(private GoogleBooksAdapter $googleBooks) {}

    public function syncGoogleBooks(string $query): int
    {
        $items = $this->googleBooks->search(
            $query,
            (int) config('services.google_books.max_results', 6),
        );

        if ($items->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($items): int {
            $source = ApiSource::query()->updateOrCreate(
                ['key' => 'google-books'],
                [
                    'name' => 'Google Books',
                    'base_url' => rtrim((string) config('services.google_books.base_url'), '/'),
                    'supported_types' => ['book'],
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
            'slug' => $literature->exists ? $literature->slug : $this->uniqueSlug($item),
            'title' => $item->title,
            'type' => 'book',
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

    private function uniqueSlug(NormalizedLiterature $item): string
    {
        $baseSlug = Str::slug($item->title);

        if (! Literature::query()->where('slug', $baseSlug)->exists()) {
            return $baseSlug;
        }

        return Str::limit($baseSlug, 180, '')
            .'-google-books-'
            .Str::slug($item->externalId);
    }
}
