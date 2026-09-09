<?php

namespace Tests\Feature\Services\Literature;

use App\Models\ApiSource;
use App\Models\Author;
use App\Models\Literature;
use App\Models\LiteratureRelation;
use App\Services\Literature\CatalogSyncService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogSyncServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_repeated_sync_updates_one_catalog_record_without_duplicates(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [$this->volume()],
            ]),
        ]);

        $firstSyncCount = app(CatalogSyncService::class)->syncGoogleBooks('Dune');
        $secondSyncCount = app(CatalogSyncService::class)->syncGoogleBooks('Dune');

        $this->assertSame(1, $firstSyncCount);
        $this->assertSame(1, $secondSyncCount);
        $this->assertDatabaseCount('api_sources', 1);
        $this->assertDatabaseCount('literatures', 1);
        $this->assertDatabaseCount('authors', 1);
        $this->assertDatabaseCount('categories', 2);
        $this->assertDatabaseCount('author_literature', 1);
        $this->assertDatabaseCount('category_literature', 2);
        $this->assertDatabaseCount('canonical_works', 1);
        $this->assertDatabaseCount('literature_source_mappings', 1);
        $this->assertDatabaseHas('literatures', [
            'external_id' => 'google-volume-1',
            'title' => 'Dune',
            'type' => 'novel',
            'publication_year' => 1965,
            'identifier' => '9780441172719',
        ]);
        Http::assertSentCount(2);
    }

    public function test_sync_matches_an_existing_google_book_by_isbn(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [$this->volume()],
            ]),
        ]);
        $source = ApiSource::factory()->create([
            'key' => 'google-books',
            'name' => 'Google Books',
        ]);
        $existing = Literature::factory()->for($source)->create([
            'external_id' => 'old-external-id',
            'slug' => 'dune',
            'title' => 'Dune',
            'identifier' => 'ISBN 9780441172719',
        ]);

        app(CatalogSyncService::class)->syncGoogleBooks('Dune');

        $this->assertDatabaseCount('literatures', 1);
        $this->assertSame('google-volume-1', $existing->refresh()->external_id);
        $this->assertSame('dune', $existing->slug);
        $this->assertSame('A science fiction classic.', $existing->synopsis);
        Http::assertSentCount(1);
    }

    public function test_repeated_anilist_sync_updates_one_manga_without_duplicates(): void
    {
        $this->configureAniList();
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([
                'data' => [
                    'Page' => [
                        'media' => [$this->manga()],
                    ],
                ],
            ]),
        ]);

        $firstSyncCount = app(CatalogSyncService::class)->syncAniList('Fullmetal Alchemist', 'manga');
        $secondSyncCount = app(CatalogSyncService::class)->syncAniList('Fullmetal Alchemist', 'manga');

        $this->assertSame(1, $firstSyncCount);
        $this->assertSame(1, $secondSyncCount);
        $this->assertDatabaseCount('api_sources', 1);
        $this->assertDatabaseCount('literatures', 1);
        $this->assertDatabaseHas('literatures', [
            'external_id' => '5114',
            'title' => 'Fullmetal Alchemist',
            'type' => 'manga',
            'identifier' => 'ANILIST:5114',
        ]);
        $this->assertDatabaseHas('api_sources', [
            'key' => 'anilist',
            'name' => 'AniList',
        ]);
        Http::assertSentCount(2);
    }

    public function test_different_sources_link_initial_variants_to_one_author(): void
    {
        $this->configureGoogleBooks();
        $this->configureAniList();
        config()->set('services.knowledge_graph.key', null);
        $volume = $this->volume();
        $volume['volumeInfo']['title'] = 'Harry Potter and the Philosopher’s Stone';
        $volume['volumeInfo']['authors'] = ['J.K Rowling'];
        $manga = $this->manga();
        $manga['title'] = ['english' => 'Harry Potter Manga Edition'];
        $manga['staff']['edges'][0]['node']['id'] = 1234;
        $manga['staff']['edges'][0]['node']['name']['full'] = 'J. K. Rowling';
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [$volume],
            ]),
            'https://graphql.anilist.co*' => Http::response([
                'data' => [
                    'Page' => [
                        'media' => [$manga],
                    ],
                ],
            ]),
        ]);

        app(CatalogSyncService::class)->syncGoogleBooks('Harry Potter');
        app(CatalogSyncService::class)->syncAniList('Harry Potter', 'manga');

        $author = Author::query()->sole();
        $this->assertSame('J.K Rowling', $author->name);
        $this->assertSame('jk rowling', $author->normalized_name);
        $this->assertSame(2, $author->literatures()->count());
        $this->assertDatabaseCount('author_aliases', 2);
        $this->assertDatabaseHas('author_aliases', [
            'author_id' => $author->id,
            'source' => 'anilist',
            'external_id' => '1234',
            'normalized_name' => 'jk rowling',
        ]);
    }

    public function test_anilist_sync_persists_bidirectional_relations_without_duplicates(): void
    {
        $this->configureAniList();
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([
                'data' => [
                    'Page' => [
                        'media' => [[
                            ...$this->manga(),
                            'relations' => [
                                'edges' => [[
                                    'relationType' => 'SEQUEL',
                                    'node' => $this->relatedManga(),
                                ]],
                            ],
                        ]],
                    ],
                ],
            ]),
        ]);

        app(CatalogSyncService::class)->syncAniList('Fullmetal Alchemist', 'manga');
        app(CatalogSyncService::class)->syncAniList('Fullmetal Alchemist', 'manga');

        $main = Literature::query()->where('external_id', '5114')->firstOrFail();
        $sequel = Literature::query()->where('external_id', '15114')->firstOrFail();

        $this->assertDatabaseCount('literatures', 2);
        $this->assertDatabaseCount('literature_relations', 2);
        $this->assertDatabaseHas('literature_relations', [
            'literature_id' => $main->id,
            'related_literature_id' => $sequel->id,
            'relation_type' => 'sequel',
            'source' => 'AniList',
        ]);
        $this->assertDatabaseHas('literature_relations', [
            'literature_id' => $sequel->id,
            'related_literature_id' => $main->id,
            'relation_type' => LiteratureRelation::inverseType('sequel'),
            'source' => 'AniList',
        ]);
    }

    public function test_sync_persists_knowledge_graph_identity_and_missing_metadata(): void
    {
        $this->configureAniList();
        $this->configureKnowledgeGraph();
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([
                'data' => [
                    'Page' => [
                        'media' => [[...$this->manga(), 'description' => null]],
                    ],
                ],
            ]),
            'https://kgsearch.googleapis.com/v1/entities:search*' => Http::response([
                'itemListElement' => [[
                    'resultScore' => 825.25,
                    'result' => [
                        '@id' => 'kg:/m/0fma',
                        '@type' => ['Thing', 'Book'],
                        'name' => 'Fullmetal Alchemist',
                        'description' => 'Japanese manga series by Hiromu Arakawa',
                        'detailedDescription' => [
                            'articleBody' => 'Two brothers search for the Philosopher’s Stone after a failed alchemical ritual.',
                            'url' => 'https://en.wikipedia.org/wiki/Fullmetal_Alchemist',
                        ],
                    ],
                ]],
            ]),
        ]);

        app(CatalogSyncService::class)->syncAniList('Fullmetal Alchemist', 'manga');

        $this->assertDatabaseHas('literatures', [
            'external_id' => '5114',
            'knowledge_graph_id' => 'kg:/m/0fma',
            'knowledge_graph_url' => 'https://en.wikipedia.org/wiki/Fullmetal_Alchemist',
            'knowledge_graph_score' => 825.25,
            'tagline' => 'Japanese manga series by Hiromu Arakawa',
            'synopsis' => 'Two brothers search for the Philosopher’s Stone after a failed alchemical ritual.',
            'synopsis_source_name' => 'Google Knowledge Graph',
        ]);
        $this->assertSame(
            ['Thing', 'Book'],
            Literature::query()->where('external_id', '5114')->firstOrFail()->knowledge_graph_types,
        );
        Http::assertSentCount(3);
    }

    public function test_repeated_comic_vine_sync_updates_one_comic_without_duplicates(): void
    {
        $this->configureComicVine();
        Http::preventStrayRequests();
        Http::fake([
            'https://comicvine.gamespot.com/api/search/*' => Http::response([
                'status_code' => 1,
                'error' => 'OK',
                'results' => [$this->comicVolume()],
            ]),
        ]);

        $firstSyncCount = app(CatalogSyncService::class)->syncComicVine('Watchmen');
        $secondSyncCount = app(CatalogSyncService::class)->syncComicVine('Watchmen');

        $this->assertSame(1, $firstSyncCount);
        $this->assertSame(1, $secondSyncCount);
        $this->assertDatabaseCount('api_sources', 1);
        $this->assertDatabaseCount('literatures', 1);
        $this->assertDatabaseHas('literatures', [
            'external_id' => '1815',
            'title' => 'Watchmen',
            'type' => 'western-comic',
            'identifier' => 'COMICVINE:4050-1815',
        ]);
        $this->assertDatabaseHas('api_sources', [
            'key' => 'comic-vine',
            'name' => 'Comic Vine',
        ]);
        Http::assertSentCount(1);
    }

    private function configureGoogleBooks(): void
    {
        config()->set([
            'services.google_books.base_url' => 'https://www.googleapis.com/books/v1',
            'services.google_books.key' => 'test-google-books-key',
            'services.google_books.max_results' => 6,
            'services.google_books.connect_timeout' => 1,
            'services.google_books.timeout' => 2,
        ]);
    }

    private function configureAniList(): void
    {
        config()->set([
            'services.anilist.base_url' => 'https://graphql.anilist.co',
            'services.anilist.max_results' => 6,
            'services.anilist.connect_timeout' => 1,
            'services.anilist.timeout' => 2,
        ]);
    }

    private function configureComicVine(): void
    {
        config()->set([
            'services.comic_vine.base_url' => 'https://comicvine.gamespot.com/api',
            'services.comic_vine.key' => 'test-comic-vine-key',
            'services.comic_vine.user_agent' => 'LiteratureSocialDiscovery/1.0 test-suite',
            'services.comic_vine.max_results' => 6,
            'services.comic_vine.cache_minutes' => 30,
            'services.comic_vine.connect_timeout' => 1,
            'services.comic_vine.timeout' => 2,
        ]);
    }

    private function configureKnowledgeGraph(): void
    {
        config()->set([
            'services.knowledge_graph.base_url' => 'https://kgsearch.googleapis.com/v1/entities:search',
            'services.knowledge_graph.key' => 'test-knowledge-graph-key',
            'services.knowledge_graph.language' => 'en',
            'services.knowledge_graph.candidate_limit' => 5,
            'services.knowledge_graph.cache_days' => 30,
            'services.knowledge_graph.connect_timeout' => 1,
            'services.knowledge_graph.timeout' => 2,
        ]);
    }

    /** @return array<string, mixed> */
    private function volume(): array
    {
        return [
            'id' => 'google-volume-1',
            'volumeInfo' => [
                'title' => 'Dune',
                'subtitle' => 'The desert planet',
                'authors' => ['Frank Herbert'],
                'publisher' => 'Chilton Books',
                'publishedDate' => '1965-08-01',
                'description' => '<p>A science fiction classic.</p>',
                'industryIdentifiers' => [
                    ['type' => 'ISBN_13', 'identifier' => '9780441172719'],
                ],
                'categories' => ['Science Fiction', 'Classics'],
                'imageLinks' => ['thumbnail' => 'https://books.google.com/cover.jpg'],
                'language' => 'en',
                'printType' => 'BOOK',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function manga(): array
    {
        return [
            'id' => 5114,
            'title' => ['english' => 'Fullmetal Alchemist'],
            'description' => 'A story about two brothers.',
            'startDate' => ['year' => 2001],
            'genres' => ['Action'],
            'countryOfOrigin' => 'JP',
            'coverImage' => ['large' => 'https://s4.anilist.co/cover.jpg'],
            'format' => 'MANGA',
            'staff' => [
                'edges' => [[
                    'role' => 'Story & Art',
                    'node' => ['name' => ['full' => 'Hiromu Arakawa']],
                ]],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function relatedManga(): array
    {
        return [
            'id' => 15114,
            'type' => 'MANGA',
            'title' => ['english' => 'Fullmetal Alchemist: The Next Chapter'],
            'description' => 'The story continues.',
            'startDate' => ['year' => 2002],
            'genres' => ['Action'],
            'countryOfOrigin' => 'JP',
            'coverImage' => ['large' => 'https://s4.anilist.co/related-cover.jpg'],
            'format' => 'MANGA',
        ];
    }

    /** @return array<string, mixed> */
    private function comicVolume(): array
    {
        return [
            'resource_type' => 'volume',
            'id' => 1815,
            'name' => 'Watchmen',
            'deck' => 'Who watches the Watchmen?',
            'description' => 'A landmark superhero story.',
            'start_year' => 1986,
            'publisher' => ['name' => 'DC Comics'],
            'image' => ['super_url' => 'https://comicvine.gamespot.com/watchmen.jpg'],
        ];
    }
}
