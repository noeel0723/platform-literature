<?php

namespace Tests\Feature\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use App\Services\Literature\MangaDexAdapter;
use App\Services\Literature\NormalizedLiterature;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MangaDexAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->configureMangaDex();
    }

    public function test_search_normalizes_a_japanese_manga_with_creator_genres_and_cover(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.mangadex.org/manga*' => Http::response([
                'result' => 'ok',
                'data' => [$this->manga()],
            ]),
        ]);

        $results = app(MangaDexAdapter::class)->search('Haikyu', 'manga', 4);

        $this->assertCount(1, $results);
        $manga = $results->first();
        $this->assertInstanceOf(NormalizedLiterature::class, $manga);
        $this->assertSame('manga-id', $manga->externalId);
        $this->assertSame('Haikyu!!', $manga->title);
        $this->assertSame('ハイキュー!!', $manga->originalTitle);
        $this->assertSame('manga', $manga->type);
        $this->assertSame(['Haruichi Furudate'], $manga->authors);
        $this->assertSame(['Sports', 'School Life'], $manga->categories);
        $this->assertSame(2012, $manga->publicationYear);
        $this->assertSame('MANGADEX:manga-id', $manga->identifier);
        $this->assertSame('https://uploads.mangadex.org/covers/manga-id/cover.jpg', $manga->coverUrl);
        $this->assertSame('A volleyball story.', $manga->synopsis);
        $this->assertSame('ja', $manga->language);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->method() === 'GET'
                && $data['title'] === 'Haikyu'
                && $data['limit'] === 4
                && $data['originalLanguage'] === ['ja']
                && $data['includes'] === ['author', 'artist', 'cover_art']
                && $data['contentRating'] === ['safe', 'suggestive'];
        });
    }

    public function test_search_classifies_korean_titles_as_manhwa_and_uses_artist_as_creator_fallback(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.mangadex.org/manga*' => Http::response([
                'result' => 'ok',
                'data' => [[
                    ...$this->manga(),
                    'id' => 'manhwa-id',
                    'attributes' => [
                        ...$this->manga()['attributes'],
                        'title' => ['en' => 'Solo Leveling', 'ko' => '나 혼자만 레벨업'],
                        'originalLanguage' => 'ko',
                    ],
                    'relationships' => [[
                        'id' => 'artist-id',
                        'type' => 'artist',
                        'attributes' => ['name' => 'DUBU'],
                    ]],
                ]],
            ]),
        ]);

        $manhwa = app(MangaDexAdapter::class)->search('Solo Leveling', 'manhwa')->first();

        $this->assertSame('manhwa', $manhwa->type);
        $this->assertSame('Manhwa', $manhwa->format);
        $this->assertSame('ko', $manhwa->language);
        $this->assertSame(['DUBU'], $manhwa->authors);
        Http::assertSent(fn (Request $request): bool => $request->data()['originalLanguage'] === ['ko']);
    }

    public function test_all_search_only_keeps_japanese_manga_and_korean_manhwa(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.mangadex.org/manga*' => Http::response([
                'result' => 'ok',
                'data' => [
                    $this->manga(),
                    [
                        ...$this->manga(),
                        'id' => 'manhua-id',
                        'attributes' => [
                            ...$this->manga()['attributes'],
                            'originalLanguage' => 'zh',
                        ],
                    ],
                ],
            ]),
        ]);

        $results = app(MangaDexAdapter::class)->search('Example', 'all');

        $this->assertCount(1, $results);
        Http::assertSent(fn (Request $request): bool => $request->data()['originalLanguage'] === ['ja', 'ko']);
    }

    public function test_missing_english_synopsis_is_left_empty_for_english_enrichment(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.mangadex.org/manga*' => Http::response([
                'result' => 'ok',
                'data' => [[
                    ...$this->manga(),
                    'attributes' => [
                        ...$this->manga()['attributes'],
                        'description' => ['ja' => '日本語の説明'],
                    ],
                ]],
            ]),
        ]);

        $manga = app(MangaDexAdapter::class)->search('Haikyu', 'manga')->first();

        $this->assertNull($manga->synopsis);
    }

    public function test_search_reports_rate_limit_as_an_unavailable_source(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.mangadex.org/manga*' => Http::response([], 429),
        ]);

        $this->expectException(LiteratureSourceUnavailable::class);
        $this->expectExceptionMessage('rate limit');

        app(MangaDexAdapter::class)->search('Haikyu', 'manga');
    }

    private function configureMangaDex(): void
    {
        config()->set([
            'services.mangadex.base_url' => 'https://api.mangadex.org',
            'services.mangadex.covers_url' => 'https://uploads.mangadex.org/covers',
            'services.mangadex.user_agent' => 'Literahaven/1.0 test-suite',
            'services.mangadex.max_results' => 6,
            'services.mangadex.cache_minutes' => 30,
            'services.mangadex.connect_timeout' => 1,
            'services.mangadex.timeout' => 2,
        ]);
    }

    /** @return array<string, mixed> */
    private function manga(): array
    {
        return [
            'id' => 'manga-id',
            'type' => 'manga',
            'attributes' => [
                'title' => ['en' => 'Haikyu!!', 'ja' => 'ハイキュー!!'],
                'altTitles' => [],
                'description' => ['en' => '<p>A <strong>volleyball</strong> story.</p>'],
                'originalLanguage' => 'ja',
                'year' => 2012,
                'tags' => [
                    ['attributes' => ['name' => ['en' => 'Sports']]],
                    ['attributes' => ['name' => ['en' => 'School Life']]],
                ],
            ],
            'relationships' => [
                [
                    'id' => 'author-id',
                    'type' => 'author',
                    'attributes' => ['name' => 'Haruichi Furudate'],
                ],
                [
                    'id' => 'artist-id',
                    'type' => 'artist',
                    'attributes' => ['name' => 'Haruichi Furudate'],
                ],
                [
                    'id' => 'cover-id',
                    'type' => 'cover_art',
                    'attributes' => ['fileName' => 'cover.jpg'],
                ],
            ],
        ];
    }
}
