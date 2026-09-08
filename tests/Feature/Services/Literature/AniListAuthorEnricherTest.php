<?php

namespace Tests\Feature\Services\Literature;

use App\Services\Literature\AniListAuthorEnricher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AniListAuthorEnricherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->configureAniList();
    }

    public function test_it_returns_an_exact_staff_match_with_a_portrait(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([
                'data' => [
                    'Page' => [
                        'staff' => [[
                            'id' => 96879,
                            'name' => [
                                'full' => 'Hiromu Arakawa',
                                'native' => '荒川 弘',
                                'userPreferred' => 'Hiromu Arakawa',
                                'alternative' => [],
                            ],
                            'image' => [
                                'large' => 'http://s4.anilist.co/hiromu-arakawa.jpg',
                            ],
                            'description' => '<b>Japanese manga artist.</b>',
                            'siteUrl' => 'https://anilist.co/staff/96879/Hiromu-Arakawa',
                        ]],
                    ],
                ],
            ]),
        ]);

        $author = app(AniListAuthorEnricher::class)->find('Hiromu Arakawa');

        $this->assertNotNull($author);
        $this->assertSame('Hiromu Arakawa', $author->name);
        $this->assertSame('https://s4.anilist.co/hiromu-arakawa.jpg', $author->imageUrl);
        $this->assertSame('Japanese manga artist.', $author->biography);
        $this->assertSame('https://anilist.co/staff/96879/Hiromu-Arakawa', $author->sourceUrl);
        Http::assertSent(fn (Request $request): bool => $request->data()['variables']['search'] === 'Hiromu Arakawa');

        app(AniListAuthorEnricher::class)->find('Hiromu Arakawa');
        Http::assertSentCount(1);
    }

    public function test_it_ignores_a_nonmatching_staff_result(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([
                'data' => [
                    'Page' => [
                        'staff' => [[
                            'name' => [
                                'full' => 'Different Creator',
                                'native' => null,
                                'userPreferred' => 'Different Creator',
                                'alternative' => [],
                            ],
                            'image' => ['large' => 'https://s4.anilist.co/different.jpg'],
                            'description' => null,
                            'siteUrl' => 'https://anilist.co/staff/1/Different-Creator',
                        ]],
                    ],
                ],
            ]),
        ]);

        $this->assertNull(app(AniListAuthorEnricher::class)->find('Hiromu Arakawa'));
    }

    private function configureAniList(): void
    {
        config()->set([
            'services.anilist.base_url' => 'https://graphql.anilist.co',
            'services.anilist.author_cache_days' => 30,
            'services.anilist.connect_timeout' => 1,
            'services.anilist.timeout' => 2,
        ]);
    }
}
