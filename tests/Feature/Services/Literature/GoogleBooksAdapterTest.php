<?php

namespace Tests\Feature\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use App\Services\Literature\GoogleBooksAdapter;
use App\Services\Literature\NormalizedLiterature;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
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
            && $request['q'] === 'Dune subject:fiction'
            && $request['maxResults'] === 4
            && $request['printType'] === 'books'
            && $request['key'] === 'test-google-books-key');
    }

    public function test_search_excludes_non_fiction_results_from_the_novel_catalog(): void
    {
        $this->configureGoogleBooks();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [[
                    'id' => 'google-non-fiction',
                    'volumeInfo' => [
                        'title' => 'The Science of Dune',
                        'categories' => ['Nonfiction'],
                        'imageLinks' => ['thumbnail' => 'http://books.google.com/thumbnail.jpg'],
                        'printType' => 'BOOK',
                    ],
                ]],
            ]),
            'https://www.wikidata.org/w/api.php*' => Http::response(['search' => []]),
        ]);

        $results = app(GoogleBooksAdapter::class)->search('The Science of Dune');

        $this->assertTrue($results->isEmpty());
    }

    public function test_removed_book_type_is_rejected(): void
    {
        $this->configureGoogleBooks();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('only supports all and novel');

        app(GoogleBooksAdapter::class)->search('Dune', requestedType: 'book');
    }

    public function test_localized_volume_is_enriched_without_overwriting_its_edition_title(): void
    {
        $this->configureGoogleBooks();
        config()->set([
            'services.work_metadata.wikidata_url' => 'https://www.wikidata.org/w/api.php',
            'services.work_metadata.content_language' => 'en',
            'services.work_metadata.wikipedia_summary_url' => 'https://{language}.wikipedia.org/api/rest_v1/page/summary/{title}',
            'services.work_metadata.user_agent' => 'LiteratureSocialDiscovery/1.0 tests',
        ]);
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if (str_starts_with($request->url(), 'https://www.googleapis.com/books/v1/volumes')) {
                return Http::response(['items' => [[
                    'id' => 'localized-volume',
                    'volumeInfo' => [
                        'title' => 'Harry Potter dan Relikui Kematian',
                        'authors' => ['J. K. Rowling'],
                        'language' => 'id',
                        'subtitle' => 'Edisi bahasa Indonesia',
                        'description' => 'Ringkasan bahasa Indonesia yang tidak boleh ditampilkan.',
                        'printType' => 'BOOK',
                    ],
                ]]]);
            }

            if (str_starts_with($request->url(), 'https://www.wikidata.org/w/api.php') && $request['action'] === 'wbsearchentities') {
                return Http::response(['search' => [[
                    'id' => 'Q46758',
                    'description' => 'fantasy novel by J. K. Rowling',
                    'match' => ['text' => 'Harry Potter dan Relikui Kematian'],
                ]]]);
            }

            if (str_starts_with($request->url(), 'https://www.wikidata.org/w/api.php')) {
                return Http::response(['entities' => ['Q46758' => [
                    'descriptions' => ['en' => ['value' => 'fantasy novel by J. K. Rowling']],
                    'claims' => ['P1476' => [[
                        'rank' => 'normal',
                        'mainsnak' => ['datavalue' => ['value' => ['text' => 'Harry Potter and the Deathly Hallows']]],
                    ]]],
                    'sitelinks' => ['enwiki' => ['title' => 'Harry Potter and the Deathly Hallows']],
                ]]]);
            }

            return Http::response([
                'extract' => 'An English supplemental summary.',
                'content_urls' => ['desktop' => [
                    'page' => 'https://en.wikipedia.org/wiki/Harry_Potter_and_the_Deathly_Hallows',
                ]],
            ]);
        });

        $book = app(GoogleBooksAdapter::class)->search('Harry Potter')->first();

        $this->assertSame('Harry Potter dan Relikui Kematian', $book->title);
        $this->assertSame('Harry Potter and the Deathly Hallows', $book->originalTitle);
        $this->assertSame('fantasy novel by J. K. Rowling', $book->tagline);
        $this->assertSame('An English supplemental summary.', $book->synopsis);
        $this->assertSame('Wikipedia EN', $book->synopsisSourceName);
        Http::assertSentCount(4);
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
            'https://www.wikidata.org/w/api.php*' => Http::response(['search' => []]),
        ]);

        $novel = app(GoogleBooksAdapter::class)->search('A Story', 6, 'novel')->first();

        $this->assertSame('novel', $novel->type);
        $this->assertSame('Novel', $novel->format);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://www.googleapis.com/books/v1/volumes')
            && $request['q'] === 'A Story subject:fiction');
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
