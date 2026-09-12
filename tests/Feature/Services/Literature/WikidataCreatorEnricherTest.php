<?php

namespace Tests\Feature\Services\Literature;

use App\Services\Literature\WikidataCreatorEnricher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataCreatorEnricherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set([
            'services.work_metadata.wikidata_sparql_url' => 'https://query.wikidata.test/sparql',
            'services.work_metadata.user_agent' => 'Literahaven/1.0 test-suite',
            'services.work_metadata.connect_timeout' => 1,
            'services.work_metadata.timeout' => 2,
        ]);
    }

    public function test_it_maps_kitsu_ids_to_wikidata_authors_and_artists(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://query.wikidata.test/sparql*' => Http::response([
                'results' => [
                    'bindings' => [
                        $this->binding('54114', 'Q113288698', 'DUBU', 'artist'),
                        $this->binding('54114', 'Q106588690', 'Chugong', 'author'),
                        $this->binding('54114', 'Q999999999', 'Q999999999', 'author'),
                        $this->binding('39293', 'Q6677355', 'Park Tae-joon', 'creator'),
                    ],
                ],
            ]),
        ]);

        $result = app(WikidataCreatorEnricher::class)->forKitsuIds(['54114', '39293', 'invalid', '54114']);

        $this->assertSame(['Chugong', 'DUBU'], collect($result['54114'])->pluck('name')->all());
        $this->assertSame(
            ['https://www.wikidata.org/entity/Q106588690', 'https://www.wikidata.org/entity/Q113288698'],
            collect($result['54114'])->pluck('sourceUrl')->all(),
        );
        $this->assertSame(['Park Tae-joon'], collect($result['39293'])->pluck('name')->all());

        Http::assertSent(function (Request $request): bool {
            $query = (string) ($request->data()['query'] ?? '');

            return $request->method() === 'GET'
                && str_contains($query, 'wdt:P11494')
                && str_contains($query, 'wdt:P50')
                && str_contains($query, 'wdt:P110')
                && str_contains($query, 'wdt:P170')
                && str_contains($query, '"54114"')
                && str_contains($query, '"39293"')
                && ($request->data()['format'] ?? null) === 'json';
        });
    }

    /** @return array<string, array{type: string, value: string}> */
    private function binding(string $externalId, string $entityId, string $name, string $role): array
    {
        return [
            'externalId' => ['type' => 'literal', 'value' => $externalId],
            'creator' => ['type' => 'uri', 'value' => "http://www.wikidata.org/entity/{$entityId}"],
            'creatorLabel' => ['type' => 'literal', 'value' => $name],
            'role' => ['type' => 'literal', 'value' => $role],
        ];
    }
}
