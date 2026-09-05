<?php

namespace Tests\Feature\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use App\Services\Literature\AniListAdapter;
use App\Services\Literature\NormalizedLiterature;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AniListAdapterTest extends TestCase
{
    public function test_search_normalizes_manga_and_skips_invalid_items(): void
    {
        $this->configureAniList();
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([
                'data' => [
                    'Page' => [
                        'media' => [
                            $this->completeManga(),
                            ['id' => 999, 'title' => []],
                        ],
                    ],
                ],
            ]),
        ]);

        $results = app(AniListAdapter::class)->search('Fullmetal Alchemist', 'manga', 4);

        $this->assertCount(1, $results);
        $manga = $results->first();
        $this->assertInstanceOf(NormalizedLiterature::class, $manga);
        $this->assertSame('5114', $manga->externalId);
        $this->assertSame('Fullmetal Alchemist', $manga->title);
        $this->assertSame('manga', $manga->type);
        $this->assertSame(['Hiromu Arakawa'], $manga->authors);
        $this->assertSame(['Action', 'Adventure'], $manga->categories);
        $this->assertSame(2001, $manga->publicationYear);
        $this->assertSame('ANILIST:5114', $manga->identifier);
        $this->assertSame('https://s4.anilist.co/file/anilistcdn/media/manga/cover/large.jpg', $manga->coverUrl);
        $this->assertSame('A story about two brothers.', $manga->synopsis);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->method() === 'POST'
                && $data['variables']['search'] === 'Fullmetal Alchemist'
                && $data['variables']['perPage'] === 4
                && $data['variables']['formats'] === ['MANGA', 'ONE_SHOT'];
        });
    }

    public function test_light_novel_search_uses_the_novel_format(): void
    {
        $this->configureAniList();
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([
                'data' => ['Page' => ['media' => []]],
            ]),
        ]);

        $results = app(AniListAdapter::class)->search('Spice and Wolf', 'light-novel');

        $this->assertTrue($results->isEmpty());
        Http::assertSent(fn (Request $request): bool => $request->data()['variables']['formats'] === ['NOVEL']);
    }

    public function test_all_format_search_uses_one_request_for_manga_and_light_novels(): void
    {
        $this->configureAniList();
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([
                'data' => ['Page' => ['media' => []]],
            ]),
        ]);

        app(AniListAdapter::class)->search('Frieren', 'all');

        Http::assertSent(fn (Request $request): bool => $request->data()['variables']['formats'] === [
            'MANGA',
            'ONE_SHOT',
            'NOVEL',
        ]);
        Http::assertSentCount(1);
    }

    public function test_search_reports_graphql_errors_as_an_unavailable_source(): void
    {
        $this->configureAniList();
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([
                'errors' => [['message' => 'Invalid request']],
            ]),
        ]);

        $this->expectException(LiteratureSourceUnavailable::class);
        $this->expectExceptionMessage('GraphQL error');

        app(AniListAdapter::class)->search('Dune', 'manga');
    }

    public function test_search_reports_rate_limit_as_an_unavailable_source(): void
    {
        $this->configureAniList();
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([], 429),
        ]);

        $this->expectException(LiteratureSourceUnavailable::class);
        $this->expectExceptionMessage('rate limit');

        app(AniListAdapter::class)->search('Dune', 'manga');
    }

    public function test_search_reports_a_connection_failure_as_an_unavailable_source(): void
    {
        $this->configureAniList();
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::failedConnection(),
        ]);

        $this->expectException(LiteratureSourceUnavailable::class);
        $this->expectExceptionMessage('could not be reached');

        app(AniListAdapter::class)->search('Dune', 'manga');
    }

    private function configureAniList(): void
    {
        config()->set([
            'services.anilist.base_url' => 'https://graphql.anilist.co',
            'services.anilist.max_results' => 6,
            'services.anilist.connect_timeout' => 1,
            'services.anilist.timeout' => 2,
        ]);
    }

    /** @return array<string, mixed> */
    private function completeManga(): array
    {
        return [
            'id' => 5114,
            'title' => [
                'english' => 'Fullmetal Alchemist',
                'romaji' => 'Hagane no Renkinjutsushi',
                'native' => 'Hagane no Renkinjutsushi',
            ],
            'description' => '<p>A story about <strong>two brothers</strong>.</p>',
            'startDate' => ['year' => 2001],
            'genres' => ['Action', 'Adventure'],
            'coverImage' => [
                'extraLarge' => 'https://s4.anilist.co/file/anilistcdn/media/manga/cover/large.jpg',
            ],
            'format' => 'MANGA',
            'staff' => [
                'edges' => [
                    [
                        'role' => 'Story & Art',
                        'node' => ['name' => ['full' => 'Hiromu Arakawa']],
                    ],
                    [
                        'role' => 'Translation',
                        'node' => ['name' => ['full' => 'Example Translator']],
                    ],
                ],
            ],
        ];
    }
}
