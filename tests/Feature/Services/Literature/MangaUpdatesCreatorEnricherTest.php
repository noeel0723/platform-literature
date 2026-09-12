<?php

namespace Tests\Feature\Services\Literature;

use App\Services\Literature\MangaUpdatesCreatorEnricher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MangaUpdatesCreatorEnricherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set([
            'services.mangaupdates.base_url' => 'https://api.mangaupdates.test/v1',
            'services.mangaupdates.user_agent' => 'Literahaven/1.0 test-suite',
            'services.mangaupdates.cache_days' => 30,
            'services.mangaupdates.connect_timeout' => 1,
            'services.mangaupdates.timeout' => 2,
        ]);
    }

    public function test_it_resolves_an_exact_manhwa_title_and_deduplicates_author_roles(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.mangaupdates.test/v1/series/search' => Http::response([
                'results' => [[
                    'record' => [
                        'series_id' => 1837085795,
                        'title' => 'Lookism',
                        'type' => 'Manhwa',
                    ],
                    'hit_title' => 'Lookism',
                ]],
            ]),
            'https://api.mangaupdates.test/v1/series/1837085795' => Http::response([
                'authors' => [
                    [
                        'name' => 'Park Tae Jun',
                        'url' => null,
                        'type' => 'Author',
                    ],
                    [
                        'name' => 'PARK TAE JUN',
                        'url' => 'https://www.mangaupdates.com/author/kxsrl5z/park-tae-jun',
                        'type' => 'Artist',
                    ],
                ],
            ]),
        ]);

        $result = app(MangaUpdatesCreatorEnricher::class)->forWorks([
            '39293' => ['title' => 'Lookism', 'type' => 'manhwa'],
        ]);

        $this->assertSame(['Park Tae Jun'], collect($result['39293'])->pluck('name')->all());
        $this->assertNull($result['39293'][0]->sourceUrl);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.mangaupdates.test/v1/series/search'
            && $request['search'] === 'Lookism');
    }
}
