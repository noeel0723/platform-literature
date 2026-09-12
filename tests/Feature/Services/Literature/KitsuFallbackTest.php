<?php

namespace Tests\Feature\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use App\Services\Literature\CatalogSyncService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KitsuFallbackTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set([
            'services.anilist.base_url' => 'https://graphql.anilist.co',
            'services.anilist.max_results' => 6,
            'services.anilist.connect_timeout' => 1,
            'services.anilist.timeout' => 2,
            'services.kitsu.base_url' => 'https://kitsu.io/api/edge',
            'services.kitsu.user_agent' => 'Literahaven/1.0 test-suite',
            'services.kitsu.max_results' => 6,
            'services.kitsu.cache_minutes' => 30,
            'services.kitsu.connect_timeout' => 1,
            'services.kitsu.timeout' => 2,
            'services.work_metadata.wikidata_sparql_url' => null,
            'services.mangaupdates.base_url' => null,
        ]);
    }

    public function test_kitsu_is_used_when_anilist_is_unavailable(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([], 503),
            'https://kitsu.io/api/edge/manga*' => Http::response($this->kitsuResponse()),
        ]);

        $count = app(CatalogSyncService::class)->syncAniList('Haikyu', 'manga');

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('api_sources', ['key' => 'kitsu', 'name' => 'Kitsu']);
        $this->assertDatabaseHas('literatures', [
            'external_id' => 'kitsu-haikyu',
            'title' => 'Haikyu!!',
            'type' => 'manga',
            'identifier' => 'KITSU:kitsu-haikyu',
        ]);
        Http::assertSentCount(2);
    }

    public function test_combined_source_error_is_reported_when_anilist_and_kitsu_fail(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([], 503),
            'https://kitsu.io/api/edge/manga*' => Http::response([], 503),
        ]);

        try {
            app(CatalogSyncService::class)->syncAniList('Haikyu', 'manga');
            $this->fail('A combined source failure should be reported.');
        } catch (LiteratureSourceUnavailable $exception) {
            $this->assertSame('AniList and Kitsu', $exception->source);
        }

        Http::assertSentCount(2);
    }

    public function test_removed_light_novel_type_is_rejected_before_requesting_any_source(): void
    {
        Http::preventStrayRequests();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('only supports all, manga, and manhwa');

        app(CatalogSyncService::class)->syncAniList('Spice and Wolf', 'light-novel');
    }

    /** @return array<string, mixed> */
    private function kitsuResponse(): array
    {
        return [
            'data' => [[
                'id' => 'kitsu-haikyu',
                'type' => 'manga',
                'attributes' => [
                    'titles' => ['en' => 'Haikyu!!', 'ja_jp' => 'ハイキュー!!'],
                    'canonicalTitle' => 'Haikyuu!!',
                    'synopsis' => 'A volleyball story.',
                    'subtype' => 'manga',
                    'startDate' => '2012-02-20',
                    'posterImage' => ['medium' => 'https://media.kitsu.app/haikyu.jpg'],
                ],
                'relationships' => ['staff' => ['data' => []]],
            ]],
            'included' => [],
        ];
    }
}
