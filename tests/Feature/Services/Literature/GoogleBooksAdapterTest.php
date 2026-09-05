<?php

namespace Tests\Feature\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use App\Services\Literature\GoogleBooksAdapter;
use App\Services\Literature\NormalizedLiterature;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleBooksAdapterTest extends TestCase
{
    public function test_search_normalizes_complete_items_and_skips_invalid_items(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'totalItems' => 2,
                'items' => [
                    $this->completeVolume(),
                    ['id' => 'missing-title', 'volumeInfo' => ['authors' => ['Nobody']]],
                ],
            ]),
        ]);

        $results = app(GoogleBooksAdapter::class)->search('Dune', 4);

        $this->assertCount(1, $results);
        $book = $results->first();
        $this->assertInstanceOf(NormalizedLiterature::class, $book);
        $this->assertSame('google-volume-1', $book->externalId);
        $this->assertSame('Dune', $book->title);
        $this->assertSame('novel', $book->type);
        $this->assertSame(['Frank Herbert'], $book->authors);
        $this->assertSame(['Science Fiction', 'Classics'], $book->categories);
        $this->assertSame(1965, $book->publicationYear);
        $this->assertSame('9780441172719', $book->identifier);
        $this->assertSame('https://books.google.com/large-cover.jpg', $book->coverUrl);
        $this->assertSame('A science fiction classic.', $book->synopsis);
        $this->assertSame('Novel', $book->format);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request['q'] === 'Dune'
            && $request['maxResults'] === 4
            && $request['printType'] === 'books'
            && $request['key'] === 'test-google-books-key');
    }

    public function test_search_keeps_non_fiction_as_a_general_book(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [[
                    'id' => 'google-non-fiction',
                    'volumeInfo' => [
                        'title' => 'The Science of Dune',
                        'categories' => ['Social Science'],
                        'imageLinks' => ['thumbnail' => 'http://books.google.com/thumbnail.jpg'],
                        'printType' => 'BOOK',
                    ],
                ]],
            ]),
        ]);

        $book = app(GoogleBooksAdapter::class)->search('The Science of Dune')->first();

        $this->assertSame('book', $book->type);
        $this->assertSame('Buku', $book->format);
        $this->assertSame('https://books.google.com/thumbnail.jpg', $book->coverUrl);
    }

    public function test_novel_search_uses_a_fiction_subject_and_classifies_ambiguous_results_as_novels(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [[
                    'id' => 'google-novel',
                    'volumeInfo' => [
                        'title' => 'A Story Without Categories',
                        'printType' => 'BOOK',
                    ],
                ]],
            ]),
        ]);

        $novel = app(GoogleBooksAdapter::class)->search('A Story', 6, 'novel')->first();

        $this->assertSame('novel', $novel->type);
        $this->assertSame('Novel', $novel->format);
        Http::assertSent(fn (Request $request): bool => $request['q'] === 'A Story subject:fiction');
    }

    public function test_search_returns_an_empty_collection_for_an_incomplete_response(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response(['totalItems' => 0]),
        ]);

        $results = app(GoogleBooksAdapter::class)->search('Unknown title');

        $this->assertTrue($results->isEmpty());
        Http::assertSentCount(1);
    }

    public function test_search_reports_rate_limit_as_an_unavailable_source(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([], 429),
        ]);

        $this->expectException(LiteratureSourceUnavailable::class);
        $this->expectExceptionMessage('rate limit');

        app(GoogleBooksAdapter::class)->search('Dune');
    }

    public function test_search_reports_a_server_error_as_an_unavailable_source(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([], 503),
        ]);

        $this->expectException(LiteratureSourceUnavailable::class);
        $this->expectExceptionMessage('HTTP 503');

        app(GoogleBooksAdapter::class)->search('Dune');
    }

    public function test_search_reports_a_connection_failure_as_an_unavailable_source(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::failedConnection(),
        ]);

        $this->expectException(LiteratureSourceUnavailable::class);
        $this->expectExceptionMessage('could not be reached');

        app(GoogleBooksAdapter::class)->search('Dune');
    }

    public function test_search_requires_an_api_key_before_sending_a_request(): void
    {
        config()->set('services.google_books.key');
        Http::preventStrayRequests();

        $this->expectException(LiteratureSourceUnavailable::class);
        $this->expectExceptionMessage('API key is not configured');

        app(GoogleBooksAdapter::class)->search('Dune');
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
    private function completeVolume(): array
    {
        return [
            'id' => 'google-volume-1',
            'volumeInfo' => [
                'title' => 'Dune',
                'subtitle' => 'The desert planet',
                'authors' => ['Frank Herbert'],
                'publisher' => 'Chilton Books',
                'publishedDate' => '1965-08-01',
                'description' => '<p>A science fiction <strong>classic</strong>.</p>',
                'industryIdentifiers' => [
                    ['type' => 'ISBN_10', 'identifier' => '0441172717'],
                    ['type' => 'ISBN_13', 'identifier' => '9780441172719'],
                ],
                'categories' => ['Science Fiction', 'Classics'],
                'imageLinks' => [
                    'extraLarge' => 'http://books.google.com/large-cover.jpg',
                    'thumbnail' => 'http://books.google.com/thumbnail.jpg',
                ],
                'language' => 'en',
                'printType' => 'BOOK',
            ],
        ];
    }
}
