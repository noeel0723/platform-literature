<?php

namespace Tests\Feature\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use App\Services\Literature\HardcoverAdapter;
use App\Services\Literature\NormalizedLiterature;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HardcoverAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set([
            'services.hardcover.base_url' => 'https://api.hardcover.app/v1/graphql',
            'services.hardcover.token' => 'test-hardcover-token',
            'services.hardcover.user_agent' => 'Literahaven/1.0 test-suite',
            'services.hardcover.cache_minutes' => 30,
            'services.hardcover.connect_timeout' => 1,
            'services.hardcover.timeout' => 2,
        ]);
    }

    public function test_search_normalizes_book_results_and_sends_backend_credentials(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.hardcover.app/v1/graphql' => Http::response([
                'data' => [
                    'search' => [
                        'results' => [[
                            'id' => 93279,
                            'title' => 'The Silver Chair',
                            'subtitle' => 'The Chronicles of Narnia',
                            'release_year' => 1953,
                            'author_names' => ['C. S. Lewis'],
                            'image' => 'http://images.hardcover.app/silver-chair.jpg',
                            'isbns' => ['9780064471091'],
                        ]],
                    ],
                ],
            ]),
        ]);

        $results = app(HardcoverAdapter::class)->search('The Silver Chair', 15);

        $this->assertCount(1, $results);
        $novel = $results->first();
        $this->assertInstanceOf(NormalizedLiterature::class, $novel);
        $this->assertSame('93279', $novel->externalId);
        $this->assertSame('The Silver Chair', $novel->title);
        $this->assertSame(['C. S. Lewis'], $novel->authors);
        $this->assertSame(1953, $novel->publicationYear);
        $this->assertSame('9780064471091', $novel->identifier);
        $this->assertSame('https://images.hardcover.app/silver-chair.jpg', $novel->coverUrl);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.hardcover.app/v1/graphql'
            && $request->hasHeader('Authorization', 'Bearer test-hardcover-token')
            && $request->hasHeader('User-Agent', 'Literahaven/1.0 test-suite')
            && $request['variables']['query'] === 'The Silver Chair'
            && $request['variables']['perPage'] === 15);
    }

    public function test_search_requires_a_token_before_sending_a_request(): void
    {
        config()->set('services.hardcover.token');
        Http::preventStrayRequests();

        $this->expectException(LiteratureSourceUnavailable::class);
        $this->expectExceptionMessage('token is not configured');

        app(HardcoverAdapter::class)->search('The Silver Chair');
    }

    public function test_search_reports_graphql_errors_as_an_unavailable_source(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.hardcover.app/v1/graphql' => Http::response([
                'errors' => [['message' => 'Search unavailable']],
            ]),
        ]);

        $this->expectException(LiteratureSourceUnavailable::class);
        $this->expectExceptionMessage('GraphQL error');

        app(HardcoverAdapter::class)->search('The Silver Chair');
    }
}
