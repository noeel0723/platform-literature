<?php

namespace Tests\Feature\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use App\Services\Literature\CatalogSyncService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MangaDexFallbackTest extends TestCase
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
            'services.mangadex.base_url' => 'https://api.mangadex.org',
            'services.mangadex.covers_url' => 'https://uploads.mangadex.org/covers',
            'services.mangadex.user_agent' => 'Literahaven/1.0 test-suite',
            'services.mangadex.max_results' => 6,
            'services.mangadex.cache_minutes' => 30,
            'services.mangadex.connect_timeout' => 1,
            'services.mangadex.timeout' => 2,
            'services.kitsu.base_url' => 'https://kitsu.io/api/edge',
            'services.kitsu.user_agent' => 'Literahaven/1.0 test-suite',
            'services.kitsu.max_results' => 6,
            'services.kitsu.cache_minutes' => 30,
            'services.kitsu.connect_timeout' => 1,
            'services.kitsu.timeout' => 2,
        ]);
    }

    public function test_mangadex_is_used_when_anilist_is_unavailable_for_manhwa(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([], 503),
            'https://api.mangadex.org/manga*' => Http::response([
                'result' => 'ok',
                'data' => [$this->manhwa()],
            ]),
        ]);

        $count = app(CatalogSyncService::class)->syncAniList('Solo Leveling', 'manhwa');

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('api_sources', [
            'key' => 'mangadex',
            'name' => 'MangaDex',
        ]);
        $this->assertDatabaseHas('literatures', [
            'external_id' => 'solo-leveling-id',
            'title' => 'Solo Leveling',
            'type' => 'manhwa',
            'identifier' => 'MANGADEX:solo-leveling-id',
        ]);
        Http::assertSentCount(2);
    }

    public function test_removed_light_novel_type_is_rejected_before_requesting_any_source(): void
    {
        Http::preventStrayRequests();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('only supports all, manga, and manhwa');

        app(CatalogSyncService::class)->syncAniList('Spice and Wolf', 'light-novel');
    }

    public function test_combined_source_error_is_reported_when_primary_and_fallback_fail(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([], 503),
            'https://api.mangadex.org/manga*' => Http::response([], 503),
            'https://kitsu.io/api/edge/manga*' => Http::response([], 503),
        ]);

        try {
            app(CatalogSyncService::class)->syncAniList('Haikyu', 'manga');
            $this->fail('A combined source failure should be reported.');
        } catch (LiteratureSourceUnavailable $exception) {
            $this->assertSame('AniList, MangaDex, and Kitsu', $exception->source);
        }

        Http::assertSentCount(3);
    }

    public function test_kitsu_is_used_when_anilist_and_mangadex_are_unavailable(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([], 503),
            'https://api.mangadex.org/manga*' => Http::response([], 503),
            'https://kitsu.io/api/edge/manga*' => Http::response([
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
            ]),
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
        Http::assertSentCount(3);
    }

    /** @return array<string, mixed> */
    private function manhwa(): array
    {
        return [
            'id' => 'solo-leveling-id',
            'type' => 'manga',
            'attributes' => [
                'title' => ['en' => 'Solo Leveling', 'ko' => '나 혼자만 레벨업'],
                'altTitles' => [],
                'description' => ['en' => 'A hunter begins leveling up.'],
                'originalLanguage' => 'ko',
                'year' => 2018,
                'tags' => [['attributes' => ['name' => ['en' => 'Action']]]],
            ],
            'relationships' => [[
                'id' => 'author-id',
                'type' => 'author',
                'attributes' => ['name' => 'Chugong'],
            ]],
        ];
    }
}
