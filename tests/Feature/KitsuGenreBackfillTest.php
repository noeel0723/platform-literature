<?php

namespace Tests\Feature;

use App\Models\ApiSource;
use App\Models\Literature;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KitsuGenreBackfillTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_enriches_existing_kitsu_comics_without_genres(): void
    {
        config()->set([
            'services.kitsu.base_url' => 'https://kitsu.io/api/edge',
            'services.kitsu.user_agent' => 'Literahaven/1.0 test-suite',
            'services.kitsu.cache_minutes' => 30,
            'services.kitsu.connect_timeout' => 1,
            'services.kitsu.timeout' => 2,
        ]);
        $source = ApiSource::factory()->create(['key' => 'kitsu', 'name' => 'Kitsu']);
        $literature = Literature::factory()->for($source)->create([
            'external_id' => '12619',
            'type' => 'manga',
            'title' => 'Haikyu!!',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://kitsu.io/api/edge/manga*' => Http::response([
                'data' => [[
                    'id' => '12619',
                    'type' => 'manga',
                    'relationships' => [
                        'genres' => ['data' => []],
                        'categories' => ['data' => [
                            ['id' => '150', 'type' => 'categories'],
                            ['id' => '156', 'type' => 'categories'],
                        ]],
                    ],
                ]],
                'included' => [
                    ['id' => '150', 'type' => 'categories', 'attributes' => ['title' => 'Action']],
                    ['id' => '156', 'type' => 'categories', 'attributes' => ['title' => 'Fantasy']],
                ],
            ]),
        ]);

        $this->artisan('literahaven:backfill-kitsu-genres', ['--limit' => 10])
            ->expectsOutput('Inspected 1 Kitsu records; enriched 1.')
            ->assertSuccessful();

        $this->assertSame(
            ['Action', 'Fantasy'],
            $literature->fresh()->categories()->orderBy('name')->pluck('name')->all(),
        );
    }
}
