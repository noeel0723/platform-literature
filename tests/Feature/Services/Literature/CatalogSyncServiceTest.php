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
}
