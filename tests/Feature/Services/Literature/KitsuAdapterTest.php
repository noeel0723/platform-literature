<?php

namespace Tests\Feature\Services\Literature;

use App\Exceptions\LiteratureSourceUnavailable;
use App\Services\Literature\KitsuAdapter;
use App\Services\Literature\NormalizedLiterature;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KitsuAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->configureKitsu();
    }

    public function test_search_normalizes_manga_with_creator_and_cover(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://kitsu.io/api/edge/manga*' => Http::response($this->payload()),
        ]);

        $results = app(KitsuAdapter::class)->search('Haikyu', 'manga', 4);

        $this->assertCount(1, $results);
        $manga = $results->first();
        $this->assertInstanceOf(NormalizedLiterature::class, $manga);
        $this->assertSame('12619', $manga->externalId);
        $this->assertSame('Haikyu!!', $manga->title);
        $this->assertSame('ハイキュー!!', $manga->originalTitle);
        $this->assertSame('manga', $manga->type);
        $this->assertSame(['Haruichi Furudate'], $manga->authors);
        $this->assertSame(2012, $manga->publicationYear);
        $this->assertSame('KITSU:12619', $manga->identifier);
        $this->assertSame('https://media.kitsu.app/haikyu-medium.jpg', $manga->coverUrl);
        $this->assertSame('https://media.kitsu.app/haikyu-hero.jpg', $manga->backdropUrl);
        $this->assertSame('A volleyball story.', $manga->synopsis);
        $this->assertSame('ja', $manga->language);
        $this->assertSame('person-id', $manga->authorDetails[0]->externalId);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->method() === 'GET'
                && $data['filter[text]'] === 'Haikyu'
                && $data['page[limit]'] === 4
                && $data['include'] === 'staff.person'
                && $request->hasHeader('Accept', 'application/vnd.api+json');
        });
    }

    public function test_search_filters_results_to_the_requested_manhwa_type(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://kitsu.io/api/edge/manga*' => Http::response([
                'data' => [
                    $this->manga(),
                    [
                        ...$this->manga(),
                        'id' => '13767',
                        'attributes' => [
                            ...$this->manga()['attributes'],
                            'titles' => ['en' => 'Solo Leveling', 'ko_kr' => '나 혼자만 레벨업'],
                            'canonicalTitle' => 'Solo Leveling',
                            'subtype' => 'manhwa',
                            'startDate' => '2018-03-04',
                        ],
                        'relationships' => ['staff' => ['data' => []]],
                    ],
                ],
                'included' => [],
            ]),
        ]);

        $results = app(KitsuAdapter::class)->search('Solo Leveling', 'manhwa');

        $this->assertCount(1, $results);
        $this->assertSame('Solo Leveling', $results->first()->title);
        $this->assertSame('manhwa', $results->first()->type);
        $this->assertSame('ko', $results->first()->language);
        $this->assertSame('Manhwa', $results->first()->format);
    }

    public function test_search_reports_rate_limit_as_an_unavailable_source(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://kitsu.io/api/edge/manga*' => Http::response([], 429),
        ]);

        $this->expectException(LiteratureSourceUnavailable::class);
        $this->expectExceptionMessage('rate limit');

        app(KitsuAdapter::class)->search('Haikyu', 'manga');
    }

    private function configureKitsu(): void
    {
        config()->set([
            'services.kitsu.base_url' => 'https://kitsu.io/api/edge',
            'services.kitsu.user_agent' => 'Literahaven/1.0 test-suite',
            'services.kitsu.max_results' => 6,
            'services.kitsu.cache_minutes' => 30,
            'services.kitsu.connect_timeout' => 1,
            'services.kitsu.timeout' => 2,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'data' => [$this->manga()],
            'included' => [
                [
                    'id' => 'staff-id',
                    'type' => 'mediaStaff',
                    'attributes' => ['role' => 'Story & Art'],
                    'relationships' => ['person' => ['data' => ['id' => 'person-id', 'type' => 'people']]],
                ],
                [
                    'id' => 'person-id',
                    'type' => 'people',
                    'attributes' => ['name' => 'Haruichi Furudate'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function manga(): array
    {
        return [
            'id' => '12619',
            'type' => 'manga',
            'attributes' => [
                'titles' => ['en' => 'Haikyu!!', 'en_jp' => 'Haikyuu!!', 'ja_jp' => 'ハイキュー!!'],
                'canonicalTitle' => 'Haikyuu!!',
                'synopsis' => '<p>A <strong>volleyball</strong> story.</p>',
                'subtype' => 'manga',
                'startDate' => '2012-02-20',
                'posterImage' => ['medium' => 'https://media.kitsu.app/haikyu-medium.jpg'],
                'coverImage' => ['original' => 'https://media.kitsu.app/haikyu-hero.jpg'],
            ],
            'relationships' => [
                'staff' => ['data' => [['id' => 'staff-id', 'type' => 'mediaStaff']]],
            ],
        ];
    }
}
