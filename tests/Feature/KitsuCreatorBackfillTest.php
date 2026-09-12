<?php

namespace Tests\Feature;

use App\Models\ApiSource;
use App\Models\Literature;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KitsuCreatorBackfillTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_enriches_existing_authorless_kitsu_manhwa(): void
    {
        Cache::flush();
        config()->set([
            'services.work_metadata.wikidata_sparql_url' => 'https://query.wikidata.test/sparql',
            'services.work_metadata.user_agent' => 'Literahaven/1.0 test-suite',
        ]);

        $source = ApiSource::factory()->create([
            'key' => 'kitsu',
            'name' => 'Kitsu',
            'supported_types' => ['manga', 'manhwa'],
        ]);
        $literature = Literature::factory()->create([
            'api_source_id' => $source->id,
            'external_id' => '54114',
            'type' => 'manhwa',
            'title' => 'Solo Leveling',
            'format' => 'Manhwa',
            'identifier' => 'KITSU:54114',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://query.wikidata.test/sparql*' => Http::response([
                'results' => [
                    'bindings' => [
                        $this->binding('Q106588690', 'Chugong', 'author'),
                        $this->binding('Q113288698', 'DUBU', 'artist'),
                    ],
                ],
            ]),
        ]);

        $this->artisan('literahaven:backfill-kitsu-creators', ['--limit' => 10])
            ->expectsOutput('Inspected 1 Kitsu records; enriched 1.')
            ->assertSuccessful();

        $this->assertSame(
            ['Chugong', 'DUBU'],
            $literature->fresh()->authors()->orderByPivot('position')->pluck('name')->all(),
        );
        $this->assertDatabaseHas('author_literature', ['literature_id' => $literature->id]);
    }

    /** @return array<string, array{type: string, value: string}> */
    private function binding(string $entityId, string $name, string $role): array
    {
        return [
            'externalId' => ['type' => 'literal', 'value' => '54114'],
            'creator' => ['type' => 'uri', 'value' => "http://www.wikidata.org/entity/{$entityId}"],
            'creatorLabel' => ['type' => 'literal', 'value' => $name],
            'role' => ['type' => 'literal', 'value' => $role],
        ];
    }
}
