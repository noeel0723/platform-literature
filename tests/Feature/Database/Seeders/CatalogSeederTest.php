<?php

namespace Tests\Feature\Database\Seeders;

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

        $this->assertDatabaseCount('api_sources', 3);
        $this->assertDatabaseCount('literatures', 6);
        $this->assertDatabaseCount('author_literature', 8);
        $this->assertDatabaseCount('category_literature', 18);
    }
}
