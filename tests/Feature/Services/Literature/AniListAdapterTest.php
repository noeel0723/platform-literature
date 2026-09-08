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
        $this->assertSame('Manga', $manga->format);
        $this->assertSame('Hiromu Arakawa', $manga->authorDetails[0]->name);
        $this->assertSame('https://s4.anilist.co/hiromu-arakawa.jpg', $manga->authorDetails[0]->imageUrl);
        $this->assertSame('96879', $manga->authorDetails[0]->externalId);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->method() === 'POST'
                && $data['variables']['search'] === 'Fullmetal Alchemist'
                && $data['variables']['perPage'] === 4
                && $data['variables']['formats'] === ['MANGA', 'ONE_SHOT']
                && $data['variables']['countryOfOrigin'] === null
                && $data['variables']['excludedCountries'] === ['KR'];
        });
    }

    public function test_search_classifies_korean_comics_as_manhwa(): void
    {
        $this->configureAniList();
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([
                'data' => [
                    'Page' => [
                        'media' => [[
                            ...$this->completeManga(),
                            'id' => 105398,
                            'title' => ['english' => 'Solo Leveling'],
                            'countryOfOrigin' => 'KR',
                        ]],
                    ],
                ],
            ]),
        ]);

        $manhwa = app(AniListAdapter::class)->search('Solo Leveling', 'manhwa')->first();

        $this->assertSame('manhwa', $manhwa->type);
        $this->assertSame('Manhwa', $manhwa->format);
        Http::assertSent(function (Request $request): bool {
            $variables = $request->data()['variables'];

            return $variables['formats'] === ['MANGA', 'ONE_SHOT']
                && $variables['countryOfOrigin'] === 'KR'
                && $variables['excludedCountries'] === null;
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

    public function test_search_normalizes_literature_relations_and_ignores_anime_nodes(): void
    {
        $this->configureAniList();
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([
                'data' => [
                    'Page' => [
                        'media' => [[
                            ...$this->completeManga(),
                            'relations' => [
                                'edges' => [
                                    [
                                        'relationType' => 'SEQUEL',
                                        'node' => $this->relatedManga(),
                                    ],
                                    [
                                        'relationType' => 'ADAPTATION',
                                        'node' => [
                                            ...$this->relatedManga(),
                                            'id' => 9999,
                                            'type' => 'ANIME',
                                        ],
                                    ],
                                ],
                            ],
                        ]],
                    ],
                ],
            ]),
        ]);

        $manga = app(AniListAdapter::class)->search('Fullmetal Alchemist', 'manga')->first();

        $this->assertCount(1, $manga->relations);
        $this->assertSame('sequel', $manga->relations[0]->type);
        $this->assertSame('Fullmetal Alchemist: The Next Chapter', $manga->relations[0]->literature->title);
        $this->assertSame('manga', $manga->relations[0]->literature->type);
        $this->assertSame([], $manga->relations[0]->literature->relations);
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
            'countryOfOrigin' => 'JP',
            'coverImage' => [
                'extraLarge' => 'https://s4.anilist.co/file/anilistcdn/media/manga/cover/large.jpg',
            ],
            'format' => 'MANGA',
            'staff' => [
                'edges' => [
                    [
                        'role' => 'Story & Art',
                        'node' => [
                            'id' => 96879,
                            'name' => ['full' => 'Hiromu Arakawa'],
                            'image' => ['large' => 'https://s4.anilist.co/hiromu-arakawa.jpg'],
                            'description' => 'Japanese manga artist.',
                            'siteUrl' => 'https://anilist.co/staff/96879/Hiromu-Arakawa',
                        ],
                    ],
                    [
                        'role' => 'Translation',
                        'node' => ['name' => ['full' => 'Example Translator']],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function relatedManga(): array
    {
        return [
            'id' => 15114,
            'type' => 'MANGA',
            'title' => [
                'english' => 'Fullmetal Alchemist: The Next Chapter',
                'romaji' => 'Hagane no Renkinjutsushi Next',
                'native' => 'Hagane no Renkinjutsushi Next',
            ],
            'description' => 'The story continues.',
            'startDate' => ['year' => 2002],
            'genres' => ['Action'],
            'countryOfOrigin' => 'JP',
            'coverImage' => ['large' => 'https://s4.anilist.co/related-cover.jpg'],
            'format' => 'MANGA',
        ];
    }
}
