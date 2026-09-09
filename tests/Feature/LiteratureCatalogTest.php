<?php

namespace Tests\Feature;

use App\Models\ApiSource;
use App\Models\Author;
use App\Models\Category;
use App\Models\Literature;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LiteratureCatalogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_catalog_page_starts_with_search_instead_of_loading_the_entire_database(): void
    {
        $this->createLiterature(
            [
                'title' => 'Bumi Manusia',
                'slug' => 'bumi-manusia',
                'type' => 'novel',
            ],
            'Google Books',
            ['Pramoedya Ananta Toer'],
            ['Fiksi sejarah'],
        );

        $response = $this->get(route('literatures.index'));

        $response
            ->assertOk()
            ->assertSeeText('Browse by format')
            ->assertSeeText('Search the catalog')
            ->assertSeeText('Literahaven')
            ->assertDontSeeText('Discover your next read')
            ->assertDontSeeText('Search books, novels, comics, manga, manhwa, and light novels in one place.')
            ->assertDontSeeText('Bumi Manusia')
            ->assertDontSeeText('Metadata sources')
            ->assertDontSeeText('Current scope')
            ->assertDontSeeText('Increments 1-3 / Catalog, reading, and reviews');
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

        $response
            ->assertOk()
            ->assertSeeText('Novel')
            ->assertSeeText('Comic')
            ->assertSeeText('Manga')
            ->assertSeeText('Manhwa')
            ->assertDontSeeText('Light Novel')
            ->assertDontSeeText($legacyBook->title);
    }

    public function test_catalog_only_displays_the_four_newest_search_matches(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response(['items' => []]),
            'https://openlibrary.org/search.json*' => Http::response(['docs' => []]),
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

        $this->assertSame(4, substr_count($response->getContent(), 'data-literature-card'));
        $response
            ->assertSeeText('Search Match 5')
            ->assertSeeText('Search Match 2')
            ->assertDontSeeText('Search Match 1')
            ->assertSeeText('Showing 4 of the best matches')
            ->assertSeeText('More');
    }

    public function test_catalog_prioritizes_canonical_books_over_guides_and_unofficial_matches(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response(['items' => []]),
            'https://openlibrary.org/search.json*' => Http::response(['docs' => []]),
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

        $response
            ->assertSeeInOrder(['The Chronicles of Narnia', 'Narnia Study Guide and Workbook'])
            ->assertSeeText('C. S. Lewis');
    }

    public function test_more_link_opens_a_compact_fifteen_item_paginated_catalog(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response(['items' => []]),
            'https://openlibrary.org/search.json*' => Http::response(['docs' => []]),
        ]);

        $source = ApiSource::factory()->create([
            'key' => 'google-books',
            'name' => 'Google Books',
        ]);

        foreach (range(1, 18) as $position) {
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

        $catalogResponse
            ->assertSeeText('More')
            ->assertSee(route('literatures.latest', ['q' => 'Narnia', 'type' => 'novel']));
        $this->assertSame(4, substr_count($catalogResponse->getContent(), 'data-literature-card'));
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://www.googleapis.com/books/v1/volumes')
            && $request['maxResults'] === 20);

        $firstPage = $this->get(route('literatures.latest', [
            'q' => 'Narnia',
            'type' => 'novel',
        ]))->assertOk();

        $this->assertSame(15, substr_count($firstPage->getContent(), 'data-literature-card-size="compact"'));
        $firstPage
            ->assertSeeText('18 matches · 15 per page')
            ->assertSeeText('Page 1 of 2')
            ->assertSeeText('Previous')
            ->assertSeeText('Next')
            ->assertSee('data-latest-literature-grid', false);

        $secondPage = $this->get(route('literatures.latest', [
            'q' => 'Narnia',
            'type' => 'novel',
            'page' => 2,
        ]))->assertOk();

        $this->assertSame(3, substr_count($secondPage->getContent(), 'data-literature-card-size="compact"'));
        $secondPage
            ->assertSeeText('Page 2 of 2')
            ->assertSee('rel="prev"', false);
    }

    public function test_catalog_can_be_filtered_by_query_and_type(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response(['items' => []]),
            'https://openlibrary.org/search.json*' => Http::response(['docs' => []]),
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

        $response
            ->assertOk()
            ->assertSeeText('Bumi Manusia')
            ->assertDontSeeText('Fullmetal Alchemist');
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

        $this->get(route('literatures.index', [
            'q' => $query,
            'type' => 'western-comic',
        ]))
            ->assertOk()
            ->assertSeeText('Watchmen');
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

        $response
            ->assertOk()
            ->assertSeeText('Watchmen')
            ->assertSeeText('Alan Moore')
            ->assertSee(route('authors.show', $literature->authors()->where('name', 'Alan Moore')->firstOrFail()), false)
            ->assertSeeText('Comic Vine')
            ->assertDontSeeText('Increment 2')
            ->assertDontSeeText('Manage your reading')
            ->assertDontSeeText('Increment 3 / Social Cataloging')
            ->assertDontSeeText('Source tracking')
            ->assertDontSeeText('Catalog details')
            ->assertDontSeeText('Edit your review')
            ->assertDontSeeText('Rate or review')
            ->assertDontSeeText('Rate in half-star steps, write a review, and protect other readers by marking spoilers.')
            ->assertDontSeeText('Explore sequels, prequels, adaptations, side stories, and related editions without losing the connection between formats.')
            ->assertDontSeeText('Start a focused conversation, respond to other readers, and mark spoilers before publishing.');
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
            'https://openlibrary.org/search.json*' => Http::response(['docs' => []]),
            'https://www.wikidata.org/w/api.php*' => Http::response(['search' => []]),
        ]);

        $response = $this->get(route('literatures.index', [
            'q' => 'Dune',
            'type' => 'novel',
        ]));

        $response
            ->assertOk()
            ->assertSeeText('Dune')
            ->assertSeeText('Frank Herbert')
            ->assertSee('Cover of Dune')
            ->assertDontSeeText('The local catalog remains available.');
        $this->assertDatabaseHas('literatures', [
            'external_id' => 'google-dune',
            'title' => 'Dune',
            'type' => 'novel',
            'cover_url' => 'https://books.google.com/dune-cover.jpg',
        ]);
        Http::assertSentCount(3);
    }

    public function test_catalog_search_uses_local_results_when_all_novel_sources_are_unavailable(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::failedConnection(),
            'https://openlibrary.org/search.json*' => Http::failedConnection(),
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

        $response
            ->assertOk()
            ->assertSeeText('Dune')
            ->assertSeeText('The local catalog remains available.')
            ->assertSeeText('Results from the local catalog are still available.');
        $this->assertDatabaseCount('literatures', 1);
        Http::assertSentCount(2);
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

        $response
            ->assertOk()
            ->assertSeeText('Fullmetal Alchemist')
            ->assertSeeText('Hiromu Arakawa')
            ->assertSeeText('AniList')
            ->assertDontSeeText('The local catalog remains available.');
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

        $response
            ->assertOk()
            ->assertSeeText('Solo Leveling')
            ->assertSeeText('Manhwa')
            ->assertDontSeeText('The local catalog remains available.');
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

        $response
            ->assertOk()
            ->assertSeeText('Watchmen')
            ->assertSeeText('Comic Vine')
            ->assertDontSeeText('The local catalog remains available.');
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
