<?php

namespace App\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use App\Models\ApiSource;
use App\Models\Author;
use App\Models\Category;
use App\Models\Literature;
use App\Models\LiteratureRelation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CatalogSyncService
{
    public function __construct(
        private GoogleBooksAdapter $googleBooks,
        private AniListAdapter $aniList,
        private MangaDexAdapter $mangaDex,
        private ComicVineAdapter $comicVine,
        private KnowledgeGraphEnricher $knowledgeGraph,
    ) {}

    public function syncGoogleBooks(string $query, string $literatureType = 'all'): int
    {
        $items = $this->googleBooks->search(
            $query,
            (int) config('services.google_books.max_results', 6),
            $literatureType,
        );

        return $this->sync(
            sourceKey: 'google-books',
            sourceName: 'Google Books',
            baseUrl: (string) config('services.google_books.base_url'),
            supportedTypes: ['book', 'novel'],
            items: $items,
        );
    }

    public function syncAniList(string $query, string $literatureType): int
    {
        if (! in_array($literatureType, ['all', 'manga', 'manhwa', 'light-novel'], true)) {
            throw new InvalidArgumentException('AniList sync only supports all, manga, manhwa, and light-novel types.');
        }

        try {
            $items = $this->aniList->search(
                $query,
                $literatureType,
                (int) config('services.anilist.max_results', 6),
            );

            return $this->sync(
                sourceKey: 'anilist',
                sourceName: 'AniList',
                baseUrl: (string) config('services.anilist.base_url'),
                supportedTypes: ['manga', 'manhwa', 'light-novel'],
                items: $items,
            );
        } catch (LiteratureSourceUnavailable $aniListException) {
            if ($literatureType === 'light-novel') {
                throw $aniListException;
            }

            try {
                return $this->syncMangaDex($query, $literatureType);
            } catch (LiteratureSourceUnavailable $mangaDexException) {
                throw new LiteratureSourceUnavailable(
                    'AniList and MangaDex',
                    'AniList and its MangaDex fallback are unavailable.',
                    $mangaDexException,
                );
            }
        }
    }

    public function syncMangaDex(string $query, string $literatureType): int
    {
        if (! in_array($literatureType, ['all', 'manga', 'manhwa'], true)) {
            throw new InvalidArgumentException('MangaDex sync only supports all, manga, and manhwa types.');
        }

        $items = $this->mangaDex->search(
            $query,
            $literatureType,
            (int) config('services.mangadex.max_results', 6),
        );

        return $this->sync(
            sourceKey: 'mangadex',
            sourceName: 'MangaDex',
            baseUrl: (string) config('services.mangadex.base_url'),
            supportedTypes: ['manga', 'manhwa'],
            items: $items,
        );
    }

    public function syncComicVine(string $query): int
    {
        $items = $this->comicVine->search(
            $query,
            (int) config('services.comic_vine.max_results', 6),
        );

        return $this->sync(
            sourceKey: 'comic-vine',
            sourceName: 'Comic Vine',
            baseUrl: (string) config('services.comic_vine.base_url'),
            supportedTypes: ['western-comic'],
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

        $enrichedItems = $items->map(fn (NormalizedLiterature $item): array => [
            'item' => $item,
            'entity' => $this->knowledgeGraph->find($item->title, $item->authors),
        ]);

        return DB::transaction(function () use (
            $sourceKey,
            $sourceName,
            $baseUrl,
            $supportedTypes,
            $enrichedItems,
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

            foreach ($enrichedItems as $enrichedItem) {
                $this->persist($source, $enrichedItem['item'], $enrichedItem['entity']);
            }

            return $enrichedItems->count();
        });
    }

    private function persist(
        ApiSource $source,
        NormalizedLiterature $item,
        ?KnowledgeGraphEntity $entity = null,
    ): Literature {
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

        $usesKnowledgeGraphSynopsis = $item->synopsis === null && $entity?->detailedDescription !== null;

        $literature->fill([
            'api_source_id' => $source->id,
            'external_id' => $item->externalId,
            'knowledge_graph_id' => $entity?->id ?? $literature->knowledge_graph_id,
            'knowledge_graph_types' => $entity?->types ?? $literature->knowledge_graph_types,
            'knowledge_graph_url' => $entity?->sourceUrl ?? $entity?->officialUrl ?? $literature->knowledge_graph_url,
            'knowledge_graph_score' => $entity?->score ?? $literature->knowledge_graph_score,
            'slug' => $literature->exists ? $literature->slug : $this->uniqueSlug($source, $item),
            'title' => $item->title,
            'original_title' => $item->originalTitle ?? $literature->original_title,
            'type' => $item->type,
            'publication_year' => $item->publicationYear ?? $literature->publication_year,
            'tagline' => $item->tagline ?? $entity?->description ?? $literature->tagline,
            'synopsis' => $item->synopsis ?? $entity?->detailedDescription ?? $literature->synopsis,
            'synopsis_source_name' => $item->synopsis !== null
                ? $item->synopsisSourceName
                : ($usesKnowledgeGraphSynopsis
                    ? 'Google Knowledge Graph'
                    : $literature->synopsis_source_name),
            'synopsis_source_url' => $item->synopsis !== null
                ? $item->synopsisSourceUrl
                : ($usesKnowledgeGraphSynopsis
                    ? $entity?->sourceUrl ?? $entity?->officialUrl
                    : $literature->synopsis_source_url),
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
        $this->syncRelations($source, $literature, $item->relations);

        return $literature;
    }

    /** @param list<NormalizedLiteratureRelation> $relations */
    private function syncRelations(ApiSource $source, Literature $literature, array $relations): void
    {
        foreach ($relations as $relation) {
            if (! array_key_exists($relation->type, LiteratureRelation::TYPE_LABELS)) {
                continue;
            }

            $relatedLiterature = $this->persist($source, $relation->literature);

            if ($relatedLiterature->is($literature)) {
                continue;
            }

            LiteratureRelation::query()->updateOrCreate(
                [
                    'literature_id' => $literature->id,
                    'related_literature_id' => $relatedLiterature->id,
                    'relation_type' => $relation->type,
                ],
                ['source' => $source->name],
            );

            LiteratureRelation::query()->updateOrCreate(
                [
                    'literature_id' => $relatedLiterature->id,
                    'related_literature_id' => $literature->id,
                    'relation_type' => LiteratureRelation::inverseType($relation->type),
                ],
                ['source' => $source->name],
            );
        }
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
