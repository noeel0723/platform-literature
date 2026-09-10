<?php

namespace Tests\Feature\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use App\Services\Literature\ComicVineAdapter;
use App\Services\Literature\NormalizedLiterature;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ComicVineAdapterTest extends TestCase
{
    public function test_search_normalizes_comic_volumes_and_skips_invalid_results(): void
    {
        $this->configureComicVine();
        Http::preventStrayRequests();
        Http::fake([
            'https://comicvine.gamespot.com/api/search/*' => Http::response([
                'status_code' => 1,
                'error' => 'OK',
                'results' => [
                    $this->completeVolume(),
                    ['resource_type' => 'issue', 'id' => 99, 'name' => 'Issue result'],
                    ['resource_type' => 'volume', 'id' => 100],
                ],
            ]),
            'https://comicvine.gamespot.com/api/issue/4000-367155/*' => Http::response([
                'status_code' => 1,
                'error' => 'OK',
                'results' => [
                    'id' => 367155,
                    'person_credits' => [
                        [
                            'role' => 'writer',
                            'person' => [
                                'id' => 40382,
                                'name' => 'Alan Moore',
                                'site_detail_url' => 'https://comicvine.gamespot.com/alan-moore/4040-40382/',
                            ],
                        ],
                        [
                            'role' => 'artist',
                            'person' => ['id' => 4941, 'name' => 'Dave Gibbons'],
                        ],
                        [
                            'role' => 'colorist',
                            'person' => ['id' => 7454, 'name' => 'John Higgins'],
                        ],
                    ],
                ],
            ]),
        ]);

        $results = app(ComicVineAdapter::class)->search('Watchmen', 4);

        $this->assertCount(1, $results);
        $comic = $results->first();
        $this->assertInstanceOf(NormalizedLiterature::class, $comic);
        $this->assertSame('1815', $comic->externalId);
        $this->assertSame('Watchmen', $comic->title);
        $this->assertSame('western-comic', $comic->type);
        $this->assertSame(['Alan Moore', 'Dave Gibbons'], $comic->authors);
        $this->assertSame(1986, $comic->publicationYear);
        $this->assertSame('DC Comics', $comic->publisher);
        $this->assertSame('COMICVINE:4050-1815', $comic->identifier);
        $this->assertSame('https://comicvine.gamespot.com/a/uploads/scale_large/watchmen.jpg', $comic->coverUrl);
        $this->assertSame('A landmark superhero story.', $comic->synopsis);
        $this->assertCount(2, $comic->authorDetails);
        $this->assertSame('40382', $comic->authorDetails[0]->externalId);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return str_contains($request->url(), '/api/search/')
                && $request->method() === 'GET'
                && $request->hasHeader('User-Agent', 'LiteratureSocialDiscovery/1.0 test-suite')
                && $data['query'] === 'Watchmen'
                && $data['resources'] === 'volume'
                && $data['format'] === 'json'
                && $data['limit'] === 4
                && $data['api_key'] === 'test-comic-vine-key';
        });
        Http::assertSentCount(2);
    }

    public function test_search_returns_an_empty_collection_when_no_volumes_are_found(): void
    {
        $this->configureComicVine();
        Http::preventStrayRequests();
        Http::fake([
            'https://comicvine.gamespot.com/api/search/*' => Http::response([
                'status_code' => 1,
                'error' => 'OK',
                'results' => [],
            ]),
        ]);

        $results = app(ComicVineAdapter::class)->search('Unknown comic');

        $this->assertTrue($results->isEmpty());
        Http::assertSentCount(1);
    }

    public function test_search_reports_api_level_errors_as_an_unavailable_source(): void
    {
        $this->configureComicVine();
        Http::preventStrayRequests();
        Http::fake([
            'https://comicvine.gamespot.com/api/search/*' => Http::response([
                'status_code' => 100,
                'error' => 'Invalid API Key',
                'results' => [],
            ]),
        ]);

        $this->expectException(LiteratureSourceUnavailable::class);
        $this->expectExceptionMessage('Invalid API Key');

        app(ComicVineAdapter::class)->search('Watchmen');
    }

    public function test_search_reports_rate_limit_as_an_unavailable_source(): void
    {
        $this->configureComicVine();
        Http::preventStrayRequests();
        Http::fake([
            'https://comicvine.gamespot.com/api/search/*' => Http::response([], 429),
        ]);

        $this->expectException(LiteratureSourceUnavailable::class);
        $this->expectExceptionMessage('rate limit');

        app(ComicVineAdapter::class)->search('Watchmen');
    }

    public function test_search_reports_a_connection_failure_as_an_unavailable_source(): void
    {
        $this->configureComicVine();
        Http::preventStrayRequests();
        Http::fake([
            'https://comicvine.gamespot.com/api/search/*' => Http::failedConnection(),
        ]);

        $this->expectException(LiteratureSourceUnavailable::class);
        $this->expectExceptionMessage('could not be reached');

        app(ComicVineAdapter::class)->search('Watchmen');
    }

    public function test_search_requires_an_api_key_before_sending_a_request(): void
    {
        config()->set('services.comic_vine.key');
        Http::preventStrayRequests();

        $this->expectException(LiteratureSourceUnavailable::class);
        $this->expectExceptionMessage('API key is not configured');

        app(ComicVineAdapter::class)->search('Watchmen');
    }

    private function configureComicVine(): void
    {
        config()->set([
            'services.comic_vine.base_url' => 'https://comicvine.gamespot.com/api',
            'services.comic_vine.key' => 'test-comic-vine-key',
            'services.comic_vine.user_agent' => 'LiteratureSocialDiscovery/1.0 test-suite',
            'services.comic_vine.max_results' => 6,
            'services.comic_vine.creator_enrichment_limit' => 4,
            'services.comic_vine.cache_minutes' => 30,
            'services.comic_vine.connect_timeout' => 1,
            'services.comic_vine.timeout' => 2,
        ]);
    }

    /** @return array<string, mixed> */
    private function completeVolume(): array
    {
        return [
            'resource_type' => 'volume',
            'id' => 1815,
            'name' => 'Watchmen',
            'deck' => '<p>Who watches the Watchmen?</p>',
            'description' => '<p>A landmark <strong>superhero</strong> story.</p>',
            'start_year' => '1986',
            'publisher' => ['name' => 'DC Comics'],
            'first_issue' => ['id' => 367155],
            'image' => [
                'super_url' => 'http://comicvine.gamespot.com/a/uploads/scale_large/watchmen.jpg',
            ],
        ];
    }
}
