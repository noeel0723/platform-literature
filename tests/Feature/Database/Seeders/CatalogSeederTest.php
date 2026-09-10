<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\ApiSource;
use App\Models\Literature;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CatalogSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_repeated_seeding_does_not_duplicate_catalog_records(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->seed(CatalogSeeder::class);

        $this->assertDatabaseCount('api_sources', 4);
        $this->assertDatabaseCount('literatures', 6);
        $this->assertDatabaseCount('author_literature', 8);
        $this->assertDatabaseCount('category_literature', 18);
        $this->assertDatabaseHas('literatures', [
            'slug' => 'bumi-manusia',
            'api_source_id' => ApiSource::query()->where('key', 'manual-curated')->valueOrFail('id'),
        ]);
    }

    public function test_moving_a_seeded_novel_to_manual_curation_preserves_its_existing_data(): void
    {
        $legacySource = ApiSource::factory()->create(['key' => 'legacy-google-books']);
        $literature = Literature::factory()->for($legacySource)->create([
            'slug' => 'bumi-manusia',
            'external_id' => 'legacy-bumi-manusia',
        ]);
        $review = Review::factory()
            ->for(User::factory())
            ->for($literature)
            ->create();

        $this->seed(CatalogSeeder::class);

        $manualSource = ApiSource::query()->where('key', 'manual-curated')->firstOrFail();
        $literature->refresh();

        $this->assertTrue($literature->apiSource->is($manualSource));
        $this->assertSame('curated-bumi-manusia-1980', $literature->external_id);
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'literature_id' => $literature->id,
        ]);
    }
}
