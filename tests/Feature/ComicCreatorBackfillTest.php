<?php

namespace Tests\Feature;

use App\Models\ApiSource;
use App\Models\Author;
use App\Models\Literature;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ComicCreatorBackfillTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set([
            'services.comic_vine.base_url' => 'https://comicvine.gamespot.com/api',
            'services.comic_vine.key' => 'test-comic-vine-key',
            'services.comic_vine.user_agent' => 'LiteratureSocialDiscovery/1.0 test-suite',
            'services.comic_vine.cache_minutes' => 30,
            'services.comic_vine.connect_timeout' => 1,
            'services.comic_vine.timeout' => 2,
            'services.knowledge_graph.key' => null,
        ]);
    }

    public function test_command_enriches_existing_comic_vine_records_without_authors(): void
    {
        $source = ApiSource::factory()->create([
            'key' => 'comic-vine',
            'name' => 'Comic Vine',
        ]);
        $literature = Literature::factory()->for($source)->create([
            'external_id' => '1815',
            'title' => 'Watchmen',
            'type' => 'western-comic',
            'identifier' => 'COMICVINE:4050-1815',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://comicvine.gamespot.com/api/volume/4050-1815/*' => Http::response([
                'status_code' => 1,
                'results' => ['id' => 1815, 'first_issue' => ['id' => 367155]],
            ]),
            'https://comicvine.gamespot.com/api/issue/4000-367155/*' => Http::response([
                'status_code' => 1,
                'results' => [
                    'person_credits' => [
                        ['role' => 'writer', 'id' => 40382, 'name' => 'Alan Moore'],
                        ['role' => 'artist', 'id' => 4941, 'name' => 'Dave Gibbons'],
                    ],
                ],
            ]),
        ]);

        $this->artisan('literahaven:backfill-comic-creators', ['--limit' => 10])
            ->expectsOutput('Inspected 1 Comic Vine records; enriched 1.')
            ->assertSuccessful();

        $this->assertSame(['Alan Moore', 'Dave Gibbons'], $literature->refresh()->authors->pluck('name')->all());
        $this->assertDatabaseCount('authors', 2);
        $this->assertDatabaseCount('author_aliases', 2);
        $this->assertDatabaseCount('canonical_works', 1);
        $this->assertSame(
            'Alan Moore',
            $literature->sourceMapping?->canonicalWork?->primaryAuthor?->name,
        );
        Http::assertSentCount(2);
    }

    public function test_command_ignores_comic_vine_records_that_already_have_authors(): void
    {
        $source = ApiSource::factory()->create(['key' => 'comic-vine']);
        $literature = Literature::factory()->for($source)->create(['type' => 'western-comic']);
        $literature->authors()->attach(Author::factory()->create(), [
            'role' => 'author',
            'position' => 0,
        ]);
        Http::preventStrayRequests();

        $this->artisan('literahaven:backfill-comic-creators')
            ->expectsOutput('Inspected 0 Comic Vine records; enriched 0.')
            ->assertSuccessful();

        Http::assertNothingSent();
    }
}
