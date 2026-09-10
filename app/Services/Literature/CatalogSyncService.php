<?php

namespace App\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use App\Models\ApiSource;
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
        private OpenLibraryAdapter $openLibrary,
        private HardcoverAdapter $hardcover,
        private AniListAdapter $aniList,
        private KitsuAdapter $kitsu,
        private ComicVineAdapter $comicVine,
        private KnowledgeGraphEnricher $knowledgeGraph,
        private AuthorNameNormalizer $authorNames,
        private AuthorEntityResolver $authors,
        private SemanticLiteratureResolver $semanticResolver,
    ) {}

    public function syncNovels(string $query, string $literatureType = 'all', ?int $limit = null): int
    {
        if (! in_array($literatureType, ['all', 'novel'], true)) {
            throw new InvalidArgumentException('Novel sync only supports all and novel catalog types.');
        }

        $normalizedLimit = $limit ?? (int) config('services.hardcover.max_results', 6);
        $synced = 0;
        $availableSources = 0;
        $lastException = null;
        $sourceAttempts = [];

        if (filled(config('services.hardcover.token'))) {
            $sourceAttempts[] = [
                'name' => 'Hardcover',
                'sync' => fn (): int => $this->syncHardcover($query, $normalizedLimit),
            ];
        }

        $sourceAttempts[] = [
            'name' => 'Open Library',
            'sync' => fn (): int => $this->syncOpenLibrary($query, $normalizedLimit),
        ];

        if (filled(config('services.google_books.key'))) {
            $sourceAttempts[] = [
                'name' => 'Google Books',
                'sync' => function () use ($query, $normalizedLimit, $literatureType): int {
                    $items = $this->googleBooks->search($query, $normalizedLimit, $literatureType);

                    return $this->sync(
                        sourceKey: 'google-books',
                        sourceName: 'Google Books',
                        baseUrl: (string) config('services.google_books.base_url'),
                        supportedTypes: ['novel'],
                        items: $items,
                    );
                },
            ];
        }

        foreach ($sourceAttempts as $sourceAttempt) {
            try {
                $synced += $sourceAttempt['sync']();
                $availableSources++;
            } catch (LiteratureSourceUnavailable $exception) {
                $lastException = $exception;
            }
        }

        if ($availableSources === 0) {
            $sourceNames = collect($sourceAttempts)
                ->pluck('name')
                ->join(', ', ' and ');

            throw new LiteratureSourceUnavailable(
                $sourceNames,
                "{$sourceNames} are unavailable.",
                $lastException,
            );
        }

        return $synced;
    }

    public function syncGoogleBooks(string $query, string $literatureType = 'all', ?int $limit = null): int
    {
        return $this->syncNovels($query, $literatureType, $limit);
    }

    public function syncOpenLibrary(string $query, ?int $limit = null): int
    {
        $items = $this->openLibrary->search(
            $query,
            $limit ?? (int) config('services.open_library.max_results', 6),
        );

        return $this->sync(
            sourceKey: 'open-library',
            sourceName: 'Open Library',
            baseUrl: (string) config('services.open_library.base_url'),
            supportedTypes: ['novel'],
            items: $items,
        );
    }

    public function syncHardcover(string $query, ?int $limit = null): int
    {
        $items = $this->hardcover->search(
            $query,
            $limit ?? (int) config('services.hardcover.max_results', 6),
        );

        return $this->sync(
            sourceKey: 'hardcover',
            sourceName: 'Hardcover',
            baseUrl: (string) config('services.hardcover.base_url'),
            supportedTypes: ['novel'],
            items: $items,
        );
    }

    public function syncAniList(string $query, string $literatureType, ?int $limit = null): int
    {
        if (! in_array($literatureType, ['all', 'manga', 'manhwa'], true)) {
            throw new InvalidArgumentException('AniList sync only supports all, manga, and manhwa types.');
        }

        try {
            $items = $this->aniList->search(
                $query,
                $literatureType,
                $limit ?? (int) config('services.anilist.max_results', 6),
            );

            return $this->sync(
                sourceKey: 'anilist',
                sourceName: 'AniList',
                baseUrl: (string) config('services.anilist.base_url'),
                supportedTypes: ['manga', 'manhwa'],
                items: $items,
            );
        } catch (LiteratureSourceUnavailable $aniListException) {
            try {
                return $this->syncKitsu($query, $literatureType, $limit);
            } catch (LiteratureSourceUnavailable $kitsuException) {
                throw new LiteratureSourceUnavailable(
                    'AniList and Kitsu',
                    'AniList and its Kitsu fallback are unavailable.',
                    $kitsuException,
                );
            }
        }
    }

    public function syncKitsu(string $query, string $literatureType, ?int $limit = null): int
    {
        if (! in_array($literatureType, ['all', 'manga', 'manhwa'], true)) {
            throw new InvalidArgumentException('Kitsu sync only supports all, manga, and manhwa types.');
        }

        $items = $this->kitsu->search(
            $query,
            $literatureType,
            $limit ?? (int) config('services.kitsu.max_results', 6),
        );

        return $this->sync(
            sourceKey: 'kitsu',
            sourceName: 'Kitsu',
            baseUrl: (string) config('services.kitsu.base_url'),
            supportedTypes: ['manga', 'manhwa'],
            items: $items,
        );
    }

    public function syncComicVine(string $query, ?int $limit = null): int
    {
        $items = $this->comicVine->search(
            $query,
            $limit ?? (int) config('services.comic_vine.max_results', 6),
        );

        return $this->sync(
            sourceKey: 'comic-vine',
            sourceName: 'Comic Vine',
            baseUrl: (string) config('services.comic_vine.base_url'),
            supportedTypes: ['western-comic'],
            items: $items,
        );
    }

    /** @return array{scanned: int, enriched: int} */
    public function backfillComicCreators(int $limit = 30): array
    {
        $source = ApiSource::query()->where('key', 'comic-vine')->first();

        if ($source === null) {
            return ['scanned' => 0, 'enriched' => 0];
        }

        $literatures = Literature::query()
            ->whereBelongsTo($source)
            ->where('type', 'western-comic')
            ->whereDoesntHave('authors')
            ->oldest('id')
            ->limit(max(1, min($limit, 100)))
            ->get();
        $enriched = 0;

        foreach ($literatures as $literature) {
            $creators = $this->comicVine->creatorsForVolume($literature->external_id);

            if ($creators === []) {
                continue;
            }

            DB::transaction(function () use ($source, $literature, $creators): void {
                $this->syncAuthors(
                    $source,
                    $literature,
                    array_map(fn (NormalizedAuthor $author): string => $author->name, $creators),
                    $creators,
                );
                $this->semanticResolver->resolve($literature);
            });
            $enriched++;
        }

        return ['scanned' => $literatures->count(), 'enriched' => $enriched];
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

        $enrichmentLimit = max(0, (int) config('services.knowledge_graph.sync_item_limit', 4));
        $enrichedItems = $items->values()->map(function (NormalizedLiterature $item, int $position) use ($enrichmentLimit): array {
            if ($position >= $enrichmentLimit) {
                return [
                    'item' => $item,
                    'entity' => null,
                    'author_entities' => [],
                ];
            }

            return [
                'item' => $item,
                'entity' => $this->knowledgeGraph->find($item->title, $item->authors),
                'author_entities' => collect($item->authors)
                    ->mapWithKeys(fn (string $authorName): array => [
                        $this->authorNames->normalize($authorName) => $this->knowledgeGraph->findAuthor($authorName),
                    ])
                    ->all(),
            ];
        });

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
                $this->persist(
                    $source,
                    $enrichedItem['item'],
                    $enrichedItem['entity'],
                    $enrichedItem['author_entities'],
                );
            }

            return $enrichedItems->count();
        });
    }

    private function persist(
        ApiSource $source,
        NormalizedLiterature $item,
        ?KnowledgeGraphEntity $entity = null,
        array $authorEntities = [],
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
            $normalizedPrimaryAuthor = $this->authorNames->normalize($item->authors[0]);

            $literature = Literature::query()
                ->whereBelongsTo($source)
                ->where('title', $item->title)
                ->when(
                    $item->publicationYear !== null,
                    fn ($query) => $query->where('publication_year', $item->publicationYear),
                )
                ->whereHas('authors', function ($query) use ($item, $normalizedPrimaryAuthor): void {
                    $query
                        ->where('normalized_name', $normalizedPrimaryAuthor)
                        ->orWhere('slug', Str::slug($item->authors[0]))
                        ->orWhereHas('aliases', fn ($aliases) => $aliases->where('normalized_name', $normalizedPrimaryAuthor));
                })
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
            'title' => Str::limit($item->title, 255, ''),
            'original_title' => $item->originalTitle === null
                ? $literature->original_title
                : Str::limit($item->originalTitle, 255, ''),
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

        $this->syncAuthors($source, $literature, $item->authors, $item->authorDetails, $authorEntities);
        $this->syncCategories($literature, $item->categories);
        $this->syncRelations($source, $literature, $item->relations);
        $this->semanticResolver->resolve($literature);

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

    /**
     * @param  list<string>  $authorNames
     * @param  list<NormalizedAuthor>  $authorDetails
     * @param  array<string, KnowledgeGraphEntity|null>  $authorEntities
     */
    private function syncAuthors(
        ApiSource $source,
        Literature $literature,
        array $authorNames,
        array $authorDetails = [],
        array $authorEntities = [],
    ): void {
        if ($authorNames === []) {
            return;
        }

        $authorLinks = [];
        $detailsByName = collect($authorDetails)->keyBy(
            fn (NormalizedAuthor $author): string => $this->authorNames->normalize($author->name),
        );

        foreach ($authorNames as $position => $authorName) {
            $normalizedName = $this->authorNames->normalize($authorName);
            $details = $detailsByName->get($normalizedName);
            $candidate = $details instanceof NormalizedAuthor
                ? $details
                : new NormalizedAuthor(name: $authorName);
            $entity = $authorEntities[$normalizedName] ?? null;
            $author = $this->authors->resolve($source->key, $candidate, $entity);

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
        $baseSlug = Str::limit(Str::slug($item->title), 255, '');
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'literature';

        if (! Literature::query()->where('slug', $baseSlug)->exists()) {
            return $baseSlug;
        }

        $suffix = '-'.$source->key.'-'.substr(hash('sha256', $item->externalId), 0, 12);

        return Str::limit($baseSlug, 255 - strlen($suffix), '').$suffix;
    }
}
