<?php

namespace Tests\Feature\Services\Literature;

use App\Models\ApiSource;
use App\Models\Literature;
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
        $this->assertDatabaseHas('literatures', [
            'external_id' => 'google-volume-1',
            'title' => 'Dune',
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
