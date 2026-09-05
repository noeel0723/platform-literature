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

    public function test_catalog_home_renders_increment_one_interface(): void
    {
        $this->createLiterature(
            [
                'title' => 'Bumi Manusia',
                'slug' => 'bumi-manusia',
                'type' => 'book',
            ],
            'Google Books',
            ['Pramoedya Ananta Toer'],
            ['Fiksi sejarah'],
        );

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSeeText('Satu rak untuk setiap cerita')
            ->assertSeeText('Bumi Manusia')
            ->assertSeeText('Google Books');
    }

    public function test_catalog_can_be_filtered_by_query_and_type(): void
    {
        $this->createLiterature(
            ['title' => 'Bumi Manusia', 'slug' => 'bumi-manusia', 'type' => 'book'],
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
            'type' => 'book',
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
        $this->createLiterature(
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
            ->assertSeeText('Comic Vine');
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
        ]);

        $response = $this->get(route('literatures.index', [
            'q' => 'Dune',
            'type' => 'book',
        ]));

        $response
            ->assertOk()
            ->assertSeeText('Dune')
            ->assertSeeText('Frank Herbert')
            ->assertSee('Sampul Dune')
            ->assertDontSeeText('Katalog lokal tetap aktif.');
        $this->assertDatabaseHas('literatures', [
            'external_id' => 'google-dune',
            'title' => 'Dune',
            'cover_url' => 'https://books.google.com/dune-cover.jpg',
        ]);
        Http::assertSentCount(1);
    }

    public function test_catalog_search_uses_local_results_when_google_books_is_unavailable(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::failedConnection(),
        ]);
        $this->createLiterature(
            ['title' => 'Dune', 'slug' => 'dune', 'type' => 'book'],
            'Google Books',
            ['Frank Herbert'],
        );

        $response = $this->get(route('literatures.index', [
            'q' => 'Dune',
            'type' => 'book',
        ]));

        $response
            ->assertOk()
            ->assertSeeText('Dune')
            ->assertSeeText('Katalog lokal tetap aktif.')
            ->assertSeeText('Hasil dari katalog lokal tetap ditampilkan.');
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
                            'coverImage' => ['large' => 'https://s4.anilist.co/fullmetal.jpg'],
                            'format' => 'MANGA',
                            'staff' => [
                                'edges' => [[
                                    'role' => 'Story & Art',
                                    'node' => ['name' => ['full' => 'Hiromu Arakawa']],
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
            ->assertDontSeeText('Katalog lokal tetap aktif.');
        $this->assertDatabaseHas('literatures', [
            'external_id' => '5114',
            'title' => 'Fullmetal Alchemist',
            'type' => 'manga',
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
}
