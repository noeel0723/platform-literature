<?php

namespace Tests\Feature;

use App\Models\ApiSource;
use App\Models\Literature;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HardcoverGenreBackfillTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_enriches_existing_hardcover_novels_without_genres(): void
    {
        config()->set([
            'services.hardcover.base_url' => 'https://api.hardcover.app/v1/graphql',
            'services.hardcover.token' => 'test-hardcover-token',
            'services.hardcover.user_agent' => 'Literahaven/1.0 test-suite',
            'services.hardcover.connect_timeout' => 1,
            'services.hardcover.timeout' => 2,
        ]);
        $source = ApiSource::factory()->create(['key' => 'hardcover', 'name' => 'Hardcover']);
        $literature = Literature::factory()->for($source)->create([
            'external_id' => '93279',
            'type' => 'novel',
            'title' => 'The Silver Chair',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.hardcover.app/v1/graphql' => Http::response([
                'data' => [
                    'books' => [[
                        'id' => 93279,
                        'cached_tags' => [
                            'Genre' => [
                                ['tag' => 'Fantasy'],
                                ['tag' => 'Classics'],
                            ],
                        ],
                    ]],
                ],
            ]),
        ]);

        $this->artisan('literahaven:backfill-hardcover-genres', ['--limit' => 10])
            ->expectsOutput('Inspected 1 Hardcover records; enriched 1.')
            ->assertSuccessful();

        $this->assertSame(
            ['Classics', 'Fantasy'],
            $literature->fresh()->categories()->orderBy('name')->pluck('name')->all(),
        );
    }
}
