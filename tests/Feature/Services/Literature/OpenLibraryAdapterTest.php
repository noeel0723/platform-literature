<?php

namespace Tests\Feature\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use App\Services\Literature\NormalizedLiterature;
use App\Services\Literature\OpenLibraryAdapter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenLibraryAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->configureOpenLibrary();
    }

    public function test_search_normalizes_an_english_edition_and_work_metadata(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://openlibrary.org/search.json*' => Http::response([
                'docs' => [$this->work()],
            ]),
        ]);

        $results = app(OpenLibraryAdapter::class)->search('Lord of the Rings', 8);

        $this->assertCount(1, $results);
        $novel = $results->first();
        $this->assertInstanceOf(NormalizedLiterature::class, $novel);
        $this->assertSame('OL27448W', $novel->externalId);
        $this->assertSame('指輪物語', $novel->title);
        $this->assertSame('The Lord of the Rings', $novel->originalTitle);
        $this->assertSame('novel', $novel->type);
        $this->assertSame(['J. R. R. Tolkien'], $novel->authors);
        $this->assertSame(['Fantasy', 'Middle Earth'], $novel->categories);
        $this->assertSame(1954, $novel->publicationYear);
        $this->assertSame('Allen & Unwin', $novel->publisher);
        $this->assertSame('9780618640157', $novel->identifier);
        $this->assertSame('https://covers.openlibrary.org/b/id/258027-L.jpg?default=false', $novel->coverUrl);
        $this->assertSame('A hobbit inherits a dangerous ring.', $novel->synopsis);
        $this->assertSame('OL26320A', $novel->authorDetails[0]->externalId);
        $this->assertSame('https://openlibrary.org/authors/OL26320A', $novel->authorDetails[0]->sourceUrl);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'https://openlibrary.org/search.json')
                && $data['q'] === 'Lord of the Rings'
                && $data['lang'] === 'en'
                && $data['limit'] === 8
                && str_contains($data['fields'], 'editions.title')
                && $request->hasHeader('User-Agent', 'Literahaven/1.0 test-suite');
        });
    }

    public function test_search_reports_rate_limit_as_an_unavailable_source(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://openlibrary.org/search.json*' => Http::response([], 429),
        ]);

        $this->expectException(LiteratureSourceUnavailable::class);
        $this->expectExceptionMessage('rate limit');

        app(OpenLibraryAdapter::class)->search('Dune');
    }

    private function configureOpenLibrary(): void
    {
        config()->set([
            'services.open_library.base_url' => 'https://openlibrary.org',
            'services.open_library.covers_url' => 'https://covers.openlibrary.org',
            'services.open_library.user_agent' => 'Literahaven/1.0 test-suite',
            'services.open_library.max_results' => 6,
            'services.open_library.cache_minutes' => 60,
            'services.open_library.connect_timeout' => 1,
            'services.open_library.timeout' => 2,
        ]);
    }

    /** @return array<string, mixed> */
    private function work(): array
    {
        return [
            'key' => '/works/OL27448W',
            'title' => '指輪物語',
            'author_name' => ['J. R. R. Tolkien'],
            'author_key' => ['OL26320A'],
            'first_publish_year' => 1954,
            'cover_i' => 258027,
            'isbn' => ['9780618640157'],
            'publisher' => ['Allen & Unwin'],
            'subject' => ['Fantasy', 'Middle Earth'],
            'first_sentence' => ['A hobbit inherits a dangerous ring.'],
            'editions' => [
                'docs' => [[
                    'key' => '/books/OL51785779M',
                    'title' => 'The Lord of the Rings',
                    'language' => ['eng'],
                ]],
            ],
        ];
    }
}
