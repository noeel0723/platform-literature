<?php

namespace Tests\Feature\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use App\Models\Literature;
use App\Services\Literature\CatalogSyncService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenLibraryFallbackTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set([
            'services.google_books.base_url' => 'https://www.googleapis.com/books/v1',
            'services.google_books.key' => 'test-google-books-key',
            'services.google_books.max_results' => 6,
            'services.google_books.connect_timeout' => 1,
            'services.google_books.timeout' => 2,
            'services.open_library.base_url' => 'https://openlibrary.org',
            'services.open_library.covers_url' => 'https://covers.openlibrary.org',
            'services.open_library.user_agent' => 'Literahaven/1.0 test-suite',
            'services.open_library.max_results' => 6,
            'services.open_library.cache_minutes' => 60,
            'services.open_library.connect_timeout' => 1,
            'services.open_library.timeout' => 2,
            'services.knowledge_graph.key' => null,
            'services.hardcover.token' => null,
        ]);
    }

    public function test_open_library_is_used_when_google_books_is_unavailable(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([], 503),
            'https://openlibrary.org/search.json*' => Http::response([
                'docs' => [$this->duneWork()],
            ]),
        ]);

        $synced = app(CatalogSyncService::class)->syncGoogleBooks('Dune');

        $this->assertSame(1, $synced);
        $this->assertDatabaseHas('api_sources', [
            'key' => 'open-library',
            'name' => 'Open Library',
        ]);
        $this->assertDatabaseHas('literatures', [
            'external_id' => 'OL893415W',
            'title' => 'Dune',
            'type' => 'novel',
            'identifier' => '9780441172719',
        ]);
        $this->assertDatabaseHas('authors', [
            'name' => 'Frank Herbert',
        ]);
        $this->assertDatabaseHas('literature_source_mappings', [
            'api_source_id' => Literature::query()->sole()->api_source_id,
            'source_external_id' => 'OL893415W',
        ]);
        Http::assertSentCount(2);
    }

    public function test_open_library_is_used_when_google_books_has_no_results(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response(['items' => []]),
            'https://openlibrary.org/search.json*' => Http::response([
                'docs' => [$this->duneWork()],
            ]),
        ]);

        $synced = app(CatalogSyncService::class)->syncGoogleBooks('Dune');

        $this->assertSame(1, $synced);
        $this->assertSame('Dune', Literature::query()->sole()->displayTitle());
        Http::assertSentCount(2);
    }

    public function test_open_library_is_aggregated_even_when_google_books_has_results(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [[
                    'id' => 'google-guide',
                    'volumeInfo' => [
                        'title' => 'A Guide to The Silver Chair',
                        'authors' => ['Example Critic'],
                        'description' => 'A critical companion to the Narnia novel.',
                        'language' => 'en',
                        'printType' => 'BOOK',
                    ],
                ]],
            ]),
            'https://openlibrary.org/search.json*' => Http::response([
                'docs' => [[
                    ...$this->duneWork(),
                    'key' => '/works/OL71078W',
                    'title' => 'The Silver Chair',
                    'author_name' => ['C. S. Lewis'],
                    'author_key' => ['OL31574A'],
                    'first_publish_year' => 1953,
                    'cover_i' => 14325438,
                    'isbn' => ['9780064471091'],
                ]],
            ]),
        ]);

        $synced = app(CatalogSyncService::class)->syncGoogleBooks('The Silver Chair');

        $this->assertSame(2, $synced);
        $this->assertDatabaseHas('literatures', [
            'external_id' => 'OL71078W',
            'title' => 'The Silver Chair',
        ]);
        Http::assertSentCount(2);
    }

    public function test_combined_unavailability_is_reported_when_both_novel_sources_fail(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([], 503),
            'https://openlibrary.org/search.json*' => Http::response([], 503),
        ]);

        try {
            app(CatalogSyncService::class)->syncGoogleBooks('Dune');
            $this->fail('The combined source exception was not thrown.');
        } catch (LiteratureSourceUnavailable $exception) {
            $this->assertSame('Google Books and Open Library', $exception->source);
            $this->assertStringContainsString('are unavailable', $exception->getMessage());
        }

        Http::assertSentCount(2);
    }

    public function test_configured_hardcover_is_used_as_a_third_novel_source(): void
    {
        config()->set([
            'services.hardcover.base_url' => 'https://api.hardcover.app/v1/graphql',
            'services.hardcover.token' => 'test-hardcover-token',
            'services.hardcover.user_agent' => 'Literahaven/1.0 test-suite',
            'services.hardcover.cache_minutes' => 1,
            'services.hardcover.connect_timeout' => 1,
            'services.hardcover.timeout' => 2,
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response(['items' => []]),
            'https://openlibrary.org/search.json*' => Http::response(['docs' => []]),
            'https://api.hardcover.app/v1/graphql' => Http::response([
                'data' => [
                    'search' => [
                        'results' => [[
                            'id' => 93279,
                            'title' => 'The Silver Chair',
                            'release_year' => 1953,
                            'author_names' => ['C. S. Lewis'],
                            'image' => 'https://images.hardcover.app/silver-chair.jpg',
                            'isbns' => ['9780064471091'],
                        ]],
                    ],
                ],
            ]),
        ]);

        $synced = app(CatalogSyncService::class)->syncGoogleBooks('The Silver Chair');

        $this->assertSame(1, $synced);
        $this->assertDatabaseHas('literatures', [
            'external_id' => '93279',
            'title' => 'The Silver Chair',
            'cover_url' => 'https://images.hardcover.app/silver-chair.jpg',
        ]);
        $this->assertDatabaseHas('api_sources', ['key' => 'hardcover']);
        Http::assertSentCount(3);
    }

    /** @return array<string, mixed> */
    private function duneWork(): array
    {
        return [
            'key' => '/works/OL893415W',
            'title' => 'Dune',
            'author_name' => ['Frank Herbert'],
            'author_key' => ['OL79034A'],
            'first_publish_year' => 1965,
            'cover_i' => 14878919,
            'isbn' => ['9780441172719'],
            'publisher' => ['Chilton Books'],
            'subject' => ['Science Fiction'],
            'first_sentence' => ['A boy becomes part of a struggle for a desert planet.'],
            'editions' => ['docs' => []],
        ];
    }
}
