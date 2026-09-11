<?php

namespace Tests\Feature;

use App\Models\ApiSource;
use App\Models\Author;
use App\Models\Category;
use App\Models\Literature;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LiteratureCatalogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_catalog_all_filter_shows_the_latest_work_from_each_supported_format(): void
    {
        foreach (Literature::TYPE_LABELS as $type => $label) {
            $this->createLiterature([
                'title' => "Latest {$label}",
                'slug' => "latest-{$type}",
                'type' => $type,
            ], "Internal {$label}", ["{$label} Author"]);
        }

        $response = $this->get(route('literatures.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Index')
            ->where('query', '')
            ->where('selectedType', '')
            ->where('canExpand', false)
            ->where('sourceWarning', null)
            ->has('literatures', 4)
            ->where('types', Literature::TYPE_LABELS)
            ->has('routes.catalog')
            ->has('routes.latest'));
    }

    public function test_catalog_exposes_only_the_supported_literature_types(): void
    {
        $legacyBook = Literature::factory()->create([
            'title' => 'Legacy General Book',
            'slug' => 'legacy-general-book',
            'type' => 'book',
        ]);
        Literature::factory()->create([
            'title' => 'Legacy Light Novel',
            'slug' => 'legacy-light-novel',
            'type' => 'light-novel',
        ]);

        $response = $this->get(route('literatures.index', [
            'type' => 'book',
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Index')
            ->where('selectedType', '')
            ->where('types', Literature::TYPE_LABELS)
            ->has('literatures', 0));

        $this->assertNotContains('Light Novel', $response->inertiaProps('types'));
        $this->assertNotContains($legacyBook->title, collect($response->inertiaProps('literatures'))->pluck('title')->all());
    }

    public function test_catalog_only_displays_the_four_newest_search_matches(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response(['items' => []]),
        ]);

        $source = ApiSource::factory()->create([
            'key' => 'google-books',
            'name' => 'Google Books',
        ]);

        foreach (range(1, 5) as $position) {
            Literature::factory()->for($source)->create([
                'title' => "Search Match {$position}",
                'slug' => "search-match-{$position}",
                'type' => 'novel',
                'updated_at' => now()->subMinutes(6 - $position),
            ]);
        }

        $response = $this->get(route('literatures.index', [
            'q' => 'Search Match',
            'type' => 'novel',
        ]))->assertOk();

        $this->assertSame(
            ['Search Match 5', 'Search Match 4', 'Search Match 3', 'Search Match 2'],
            collect($response->inertiaProps('literatures'))->pluck('title')->all(),
        );
        $this->assertTrue($response->inertiaProps('canExpand'));
    }

    public function test_catalog_prioritizes_canonical_books_over_guides_and_unofficial_matches(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response(['items' => []]),
        ]);

        $source = ApiSource::factory()->create([
            'key' => 'google-books',
            'name' => 'Google Books',
        ]);
        Literature::factory()->for($source)->create([
            'title' => 'Narnia Study Guide and Workbook',
            'slug' => 'narnia-study-guide-and-workbook',
            'type' => 'novel',
            'publisher' => 'Example Learning',
            'identifier' => '9780000000001',
            'cover_url' => 'https://images.example.test/narnia-guide.jpg',
        ]);
        $official = Literature::factory()->for($source)->create([
            'title' => 'The Chronicles of Narnia',
            'slug' => 'the-chronicles-of-narnia',
            'type' => 'novel',
            'publisher' => 'HarperCollins',
            'identifier' => '9780066238500',
            'cover_url' => 'https://images.example.test/narnia.jpg',
            'knowledge_graph_id' => 'kg:/m/02n4h',
        ]);
        $author = Author::factory()->create([
            'name' => 'C. S. Lewis',
            'slug' => 'c-s-lewis',
        ]);
        $official->authors()->attach($author, ['role' => 'author', 'position' => 0]);

        $response = $this->get(route('literatures.index', [
            'q' => 'Narnia',
            'type' => 'novel',
        ]))->assertOk();

        $result = collect($response->inertiaProps('literatures'))->first();

        $this->assertSame('The Chronicles of Narnia', $result['title']);
        $this->assertSame('C. S. Lewis', $result['author']);
    }

    public function test_more_link_opens_a_compact_eighteen_item_paginated_catalog(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response(['items' => []]),
        ]);

        $source = ApiSource::factory()->create([
            'key' => 'google-books',
            'name' => 'Google Books',
        ]);

        foreach (range(1, 20) as $position) {
            Literature::factory()->for($source)->create([
                'title' => "Narnia Result {$position}",
                'slug' => "narnia-result-{$position}",
                'type' => 'novel',
            ]);
        }

        $catalogResponse = $this->get(route('literatures.index', [
            'q' => 'Narnia',
            'type' => 'novel',
        ]))->assertOk();

        $this->assertTrue($catalogResponse->inertiaProps('canExpand'));
        $this->assertSame(
            route('literatures.latest', ['q' => 'Narnia', 'type' => 'novel']),
            $catalogResponse->inertiaProps('routes.latest'),
        );
        $this->assertCount(4, $catalogResponse->inertiaProps('literatures'));
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://www.googleapis.com/books/v1/volumes')
            && $request['maxResults'] === 20);

        $firstPage = $this->get(route('literatures.latest', [
            'q' => 'Narnia',
            'type' => 'novel',
        ]));

        $firstPage->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Latest')
            ->where('query', 'Narnia')
            ->where('selectedType', 'novel')
            ->where('literatures.total', 20)
            ->where('literatures.per_page', 18)
            ->where('literatures.current_page', 1)
            ->where('literatures.last_page', 2)
            ->where('literatures.prev_page_url', null)
            ->has('literatures.next_page_url')
            ->has('literatures.data', 18)
            ->has('routes.catalog'));

        $secondPage = $this->get(route('literatures.latest', [
            'q' => 'Narnia',
            'type' => 'novel',
            'page' => 2,
        ]));

        $secondPage->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Latest')
            ->where('literatures.current_page', 2)
            ->where('literatures.last_page', 2)
            ->has('literatures.prev_page_url')
            ->where('literatures.next_page_url', null)
            ->has('literatures.data', 2));
    }

    public function test_catalog_can_be_filtered_by_query_and_type(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response(['items' => []]),
        ]);

        $this->createLiterature(
            ['title' => 'Bumi Manusia', 'slug' => 'bumi-manusia', 'type' => 'novel'],
            'Google Books',
            ['Pramoedya Ananta Toer'],
        );
        $this->createLiterature(
            ['title' => 'Fullmetal Alchemist', 'slug' => 'fullmetal-alchemist', 'type' => 'manga'],
            'AniList',
            ['Hiromu Arakawa'],
        );

        $response = $this->get(route('literatures.index', [
            'q' => 'bumi',
            'type' => 'novel',
        ]));

        $this->assertSame(
            ['Bumi Manusia'],
            collect($response->inertiaProps('literatures'))->pluck('title')->all(),
        );
    }

    #[TestWith(['Alan Moore'])]
    #[TestWith(['Misteri'])]
    public function test_catalog_can_be_searched_by_author_or_category(string $query): void
    {
        config()->set('services.comic_vine.key', null);

        $this->createLiterature(
            ['title' => 'Watchmen', 'slug' => 'watchmen', 'type' => 'western-comic'],
            'Comic Vine',
            ['Alan Moore'],
            ['Misteri'],
        );

        $response = $this->get(route('literatures.index', [
            'q' => $query,
            'type' => 'western-comic',
        ]));

        $this->assertSame('Watchmen', $response->inertiaProps('literatures.0.title'));
    }

    public function test_literature_detail_renders_catalog_metadata(): void
    {
        $literature = $this->createLiterature(
            ['title' => 'Watchmen', 'slug' => 'watchmen', 'type' => 'western-comic'],
            'Comic Vine',
            ['Alan Moore', 'Dave Gibbons'],
            ['Misteri'],
        );

        $response = $this->get(route('literatures.show', 'watchmen'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Show')
            ->where('literature.title', 'Watchmen')
            ->where('literature.author', 'Alan Moore & Dave Gibbons')
            ->where('literature.source', 'Comic Vine')
            ->where('literature.author_links.0.url', route('authors.show', $literature->authors()->where('name', 'Alan Moore')->firstOrFail()))
            ->where('viewer.authenticated', false)
            ->where('ratingSummary.count', 0)
            ->has('routes.review_update')
            ->has('routes.discussion_store'));
    }

    public function test_unknown_literature_returns_not_found(): void
    {
        $this->get(route('literatures.show', 'tidak-tersedia'))
            ->assertNotFound();
    }

    public function test_catalog_search_imports_google_books_results_into_the_internal_catalog(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [[
                    'id' => 'google-dune',
                    'volumeInfo' => [
                        'title' => 'Dune',
                        'authors' => ['Frank Herbert'],
                        'publishedDate' => '1965',
                        'industryIdentifiers' => [
                            ['type' => 'ISBN_13', 'identifier' => '9780441172719'],
                        ],
                        'categories' => ['Science Fiction'],
                        'imageLinks' => ['thumbnail' => 'https://books.google.com/dune-cover.jpg'],
                        'language' => 'en',
                        'printType' => 'BOOK',
                    ],
                ]],
            ]),
            'https://www.wikidata.org/w/api.php*' => Http::response(['search' => []]),
        ]);

        $response = $this->get(route('literatures.index', [
            'q' => 'Dune',
            'type' => 'novel',
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Index')
            ->where('sourceWarning', null)
            ->has('literatures', 1, fn (Assert $literature) => $literature
                ->where('title', 'Dune')
                ->where('author', 'Frank Herbert')
                ->where('cover_url', 'https://books.google.com/dune-cover.jpg')
                ->etc()));
        $this->assertDatabaseHas('literatures', [
            'external_id' => 'google-dune',
            'title' => 'Dune',
            'type' => 'novel',
            'cover_url' => 'https://books.google.com/dune-cover.jpg',
        ]);
        Http::assertSentCount(2);
    }

    public function test_catalog_search_uses_local_results_when_all_novel_sources_are_unavailable(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::failedConnection(),
        ]);
        $this->createLiterature(
            ['title' => 'Dune', 'slug' => 'dune', 'type' => 'novel'],
            'Google Books',
            ['Frank Herbert'],
        );

        $response = $this->get(route('literatures.index', [
            'q' => 'Dune',
            'type' => 'novel',
        ]));

        $this->assertSame('Dune', $response->inertiaProps('literatures.0.title'));
        $this->assertStringContainsString(
            'Results from the local catalog are still available.',
            $response->inertiaProps('sourceWarning'),
        );
        $this->assertDatabaseCount('literatures', 1);
        Http::assertSentCount(1);
    }

    public function test_catalog_search_imports_anilist_manga_into_the_internal_catalog(): void
    {
        $this->configureAniList();
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([
                'data' => [
                    'Page' => [
                        'media' => [[
                            'id' => 5114,
                            'title' => ['english' => 'Fullmetal Alchemist'],
                            'description' => 'A story about two brothers.',
                            'startDate' => ['year' => 2001],
                            'genres' => ['Action'],
                            'countryOfOrigin' => 'JP',
                            'coverImage' => ['large' => 'https://s4.anilist.co/fullmetal.jpg'],
                            'format' => 'MANGA',
                            'staff' => [
                                'edges' => [[
                                    'role' => 'Story & Art',
                                    'node' => [
                                        'name' => ['full' => 'Hiromu Arakawa'],
                                        'image' => ['large' => 'https://s4.anilist.co/hiromu-arakawa.jpg'],
                                        'description' => 'Japanese manga artist.',
                                        'siteUrl' => 'https://anilist.co/staff/96879/Hiromu-Arakawa',
                                    ],
                                ]],
                            ],
                        ]],
                    ],
                ],
            ]),
        ]);

        $response = $this->get(route('literatures.index', [
            'q' => 'Fullmetal Alchemist',
            'type' => 'manga',
        ]));

        $this->assertSame('Fullmetal Alchemist', $response->inertiaProps('literatures.0.title'));
        $this->assertSame('Hiromu Arakawa', $response->inertiaProps('literatures.0.author'));
        $this->assertSame('AniList', $response->inertiaProps('literatures.0.source'));
        $this->assertNull($response->inertiaProps('sourceWarning'));
        $this->assertDatabaseHas('literatures', [
            'external_id' => '5114',
            'title' => 'Fullmetal Alchemist',
            'type' => 'manga',
        ]);
        $this->assertDatabaseHas('authors', [
            'name' => 'Hiromu Arakawa',
            'image_url' => 'https://s4.anilist.co/hiromu-arakawa.jpg',
        ]);
        Http::assertSentCount(1);
    }

    public function test_catalog_search_imports_anilist_manhwa_separately_from_manga(): void
    {
        $this->configureAniList();
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([
                'data' => [
                    'Page' => [
                        'media' => [[
                            'id' => 105398,
                            'title' => ['english' => 'Solo Leveling'],
                            'description' => 'A Korean action fantasy series.',
                            'startDate' => ['year' => 2018],
                            'genres' => ['Action', 'Fantasy'],
                            'countryOfOrigin' => 'KR',
                            'coverImage' => ['extraLarge' => 'https://s4.anilist.co/solo-leveling.jpg'],
                            'format' => 'MANGA',
                            'staff' => ['edges' => []],
                        ]],
                    ],
                ],
            ]),
        ]);

        $response = $this->get(route('literatures.index', [
            'q' => 'Solo Leveling',
            'type' => 'manhwa',
        ]));

        $this->assertSame('Solo Leveling', $response->inertiaProps('literatures.0.title'));
        $this->assertSame('Manhwa', $response->inertiaProps('literatures.0.type_label'));
        $this->assertNull($response->inertiaProps('sourceWarning'));
        $this->assertDatabaseHas('literatures', [
            'external_id' => '105398',
            'title' => 'Solo Leveling',
            'type' => 'manhwa',
            'format' => 'Manhwa',
        ]);
        Http::assertSentCount(1);
    }

    public function test_catalog_search_imports_comic_vine_volumes_into_the_internal_catalog(): void
    {
        $this->configureComicVine();
        Http::preventStrayRequests();
        Http::fake([
            'https://comicvine.gamespot.com/api/search/*' => Http::response([
                'status_code' => 1,
                'error' => 'OK',
                'results' => [[
                    'resource_type' => 'volume',
                    'id' => 1815,
                    'name' => 'Watchmen',
                    'deck' => 'Who watches the Watchmen?',
                    'description' => 'A landmark superhero story.',
                    'start_year' => 1986,
                    'publisher' => ['name' => 'DC Comics'],
                    'image' => ['super_url' => 'https://comicvine.gamespot.com/watchmen.jpg'],
                ]],
            ]),
        ]);

        $response = $this->get(route('literatures.index', [
            'q' => 'Watchmen',
            'type' => 'western-comic',
        ]));

        $this->assertSame('Watchmen', $response->inertiaProps('literatures.0.title'));
        $this->assertSame('Comic Vine', $response->inertiaProps('literatures.0.source'));
        $this->assertNull($response->inertiaProps('sourceWarning'));
        $this->assertDatabaseHas('literatures', [
            'external_id' => '1815',
            'title' => 'Watchmen',
            'type' => 'western-comic',
        ]);
        Http::assertSentCount(1);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $authors
     * @param  array<int, string>  $categories
     */
    private function createLiterature(
        array $attributes,
        string $sourceName,
        array $authors,
        array $categories = [],
    ): Literature {
        $source = ApiSource::factory()->create([
            'key' => Str::slug($sourceName),
            'name' => $sourceName,
        ]);
        $literature = Literature::factory()->for($source)->create($attributes);

        foreach ($authors as $position => $name) {
            $author = Author::factory()->create([
                'name' => $name,
                'slug' => Str::slug($name),
            ]);

            $literature->authors()->attach($author, [
                'role' => 'author',
                'position' => $position,
            ]);
        }

        foreach ($categories as $name) {
            $category = Category::factory()->create([
                'name' => $name,
                'slug' => Str::slug($name),
            ]);

            $literature->categories()->attach($category);
        }

        return $literature;
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
}
