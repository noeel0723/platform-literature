<?php

namespace Tests\Feature\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use App\Services\Literature\MetronAdapter;
use App\Services\Literature\NormalizedLiterature;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetronAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->configureMetron();
    }

    public function test_search_enriches_a_series_with_cover_and_primary_creators(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://metron.cloud/api/series/?*' => Http::response([
                'count' => 1,
                'results' => [[
                    'id' => 101,
                    'series' => 'Watchmen (1986)',
                    'year_began' => 1986,
                    'publisher' => ['name' => 'DC Comics'],
                    'series_type' => ['name' => 'Limited Series'],
                    'cv_id' => 1815,
                ]],
            ]),
            'https://metron.cloud/api/series/101/' => Http::response([
                'id' => 101,
                'series' => 'Watchmen (1986)',
                'desc' => '<p>A landmark superhero story.</p>',
                'genres' => [['name' => 'Superhero']],
                'language' => ['name' => 'English'],
                'resource_url' => 'https://metron.cloud/series/watchmen/',
            ]),
            'https://metron.cloud/api/series/101/issue_list/' => Http::response([
                'results' => [['id' => 501, 'number' => '1']],
            ]),
            'https://metron.cloud/api/issue/501/' => Http::response([
                'id' => 501,
                'image' => ['large_url' => 'http://static.metron.cloud/watchmen.jpg'],
                'credits' => [
                    [
                        'creator' => [
                            'id' => 11,
                            'name' => 'Alan Moore',
                            'resource_url' => 'https://metron.cloud/creator/alan-moore/',
                        ],
                        'role' => [['name' => 'Writer']],
                    ],
                    [
                        'creator' => ['id' => 12, 'name' => 'Dave Gibbons'],
                        'role' => [['name' => 'Artist']],
                    ],
                    [
                        'creator' => ['id' => 13, 'name' => 'John Higgins'],
                        'role' => [['name' => 'Colorist']],
                    ],
                ],
            ]),
        ]);

        $results = app(MetronAdapter::class)->search('Watchmen', 6);

        $this->assertCount(1, $results);
        $comic = $results->first();
        $this->assertInstanceOf(NormalizedLiterature::class, $comic);
        $this->assertSame('101', $comic->externalId);
        $this->assertSame('Watchmen', $comic->title);
        $this->assertSame(['Alan Moore', 'Dave Gibbons'], $comic->authors);
        $this->assertSame(['Superhero'], $comic->categories);
        $this->assertSame('COMICVINE:4050-1815', $comic->identifier);
        $this->assertSame('https://static.metron.cloud/watchmen.jpg', $comic->coverUrl);
        $this->assertSame('A landmark superhero story.', $comic->synopsis);
        $this->assertCount(2, $comic->authorDetails);
        $this->assertSame('11', $comic->authorDetails[0]->externalId);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://metron.cloud/api/series/?q=Watchmen'
                && $request->hasHeader('Authorization', 'Bearer test-metron-token')
                && $request->hasHeader('User-Agent', 'LiteratureSocialDiscovery/1.0 test-suite');
        });
        Http::assertSentCount(4);
    }

    public function test_search_keeps_list_result_when_optional_detail_enrichment_fails(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://metron.cloud/api/series/?*' => Http::response([
                'results' => [[
                    'id' => 101,
                    'series' => 'Watchmen (1986)',
                    'year_began' => 1986,
                    'publisher' => ['name' => 'DC Comics'],
                    'cv_id' => 1815,
                ]],
            ]),
            'https://metron.cloud/api/series/101/' => Http::response([], 503),
        ]);

        $comic = app(MetronAdapter::class)->search('Watchmen')->sole();

        $this->assertSame('Watchmen', $comic->title);
        $this->assertSame([], $comic->authors);
        $this->assertNull($comic->coverUrl);
        Http::assertSentCount(2);
    }

    public function test_search_uses_basic_auth_when_a_token_is_not_configured(): void
    {
        config()->set([
            'services.metron.token' => null,
            'services.metron.username' => 'reader',
            'services.metron.password' => 'secret',
            'services.metron.detail_enrichment_limit' => 0,
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://metron.cloud/api/series/?*' => Http::response(['results' => []]),
        ]);

        app(MetronAdapter::class)->search('Watchmen');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader(
            'Authorization',
            'Basic '.base64_encode('reader:secret'),
        ));
    }

    public function test_search_requires_configured_credentials(): void
    {
        config()->set([
            'services.metron.token' => null,
            'services.metron.username' => null,
            'services.metron.password' => null,
        ]);
        Http::preventStrayRequests();

        $this->expectException(LiteratureSourceUnavailable::class);
        $this->expectExceptionMessage('credentials are not configured');

        app(MetronAdapter::class)->search('Watchmen');
    }

    public function test_search_reports_rejected_credentials(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://metron.cloud/api/series/?*' => Http::response([], 401),
        ]);

        $this->expectException(LiteratureSourceUnavailable::class);
        $this->expectExceptionMessage('rejected the configured credentials');

        app(MetronAdapter::class)->search('Watchmen');
    }

    private function configureMetron(): void
    {
        config()->set([
            'services.metron.base_url' => 'https://metron.cloud/api',
            'services.metron.token' => 'test-metron-token',
            'services.metron.username' => null,
            'services.metron.password' => null,
            'services.metron.user_agent' => 'LiteratureSocialDiscovery/1.0 test-suite',
            'services.metron.max_results' => 6,
            'services.metron.detail_enrichment_limit' => 4,
            'services.metron.cache_minutes' => 30,
            'services.metron.connect_timeout' => 1,
            'services.metron.timeout' => 2,
        ]);
    }
}
