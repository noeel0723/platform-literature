<?php

namespace Tests\Feature\Services\Literature;

use App\Models\Author;
use App\Models\AuthorAlias;
use App\Models\Literature;
use App\Models\User;
use App\Services\Literature\AuthorEntityConsolidator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthorEntityConsolidatorTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_exact_name_variants_merge_without_losing_literature_favorites_aliases_or_metadata(): void
    {
        $canonicalLiterature = Literature::factory()->create();
        $duplicateLiterature = Literature::factory()->create();
        $canonical = Author::factory()->create([
            'name' => 'J. K. Rowling',
            'normalized_name' => 'jk rowling',
            'biography' => 'British author.',
            'image_url' => null,
        ]);
        $duplicate = Author::factory()->create([
            'name' => 'JK Rowling',
            'normalized_name' => 'jk rowling',
            'biography' => null,
            'image_url' => 'https://example.com/rowling.jpg',
        ]);
        $canonical->literatures()->attach($canonicalLiterature, ['role' => 'author', 'position' => 0]);
        $duplicate->literatures()->attach($duplicateLiterature, ['role' => 'author', 'position' => 0]);
        $canonical->literatures()->attach($duplicateLiterature, ['role' => null, 'position' => 1]);
        $user = User::factory()->create();
        $user->favoriteAuthors()->attach($duplicate, ['position' => 0]);
        AuthorAlias::factory()->for($duplicate)->create([
            'name' => 'J.K Rowling',
            'normalized_name' => 'jk rowling',
            'source' => 'google-books',
            'external_id' => null,
        ]);

        $summary = app(AuthorEntityConsolidator::class)->consolidate();

        $this->assertSame([
            'groups' => 1,
            'merged_authors' => 1,
            'skipped_groups' => 0,
        ], $summary);
        $this->assertModelMissing($duplicate);
        $this->assertDatabaseCount('authors', 1);
        $this->assertDatabaseHas('authors', [
            'id' => $canonical->id,
            'name' => 'J. K. Rowling',
            'biography' => 'British author.',
            'image_url' => 'https://example.com/rowling.jpg',
        ]);
        $this->assertDatabaseCount('author_literature', 2);
        $this->assertDatabaseHas('author_literature', [
            'author_id' => $canonical->id,
            'literature_id' => $duplicateLiterature->id,
            'role' => 'author',
            'position' => 0,
        ]);
        $this->assertDatabaseHas('user_favorite_authors', [
            'user_id' => $user->id,
            'author_id' => $canonical->id,
        ]);
        $this->assertDatabaseHas('author_aliases', [
            'author_id' => $canonical->id,
            'name' => 'JK Rowling',
            'source' => 'legacy-import',
        ]);
        $this->assertDatabaseHas('author_aliases', [
            'author_id' => $canonical->id,
            'name' => 'J.K Rowling',
            'source' => 'google-books',
        ]);
    }

    public function test_conflicting_external_entities_are_not_automatically_merged(): void
    {
        Author::factory()->create([
            'name' => 'Alex Kim',
            'normalized_name' => 'alex kim',
            'external_entity_id' => 'kg:alex-kim-author',
        ]);
        Author::factory()->create([
            'name' => 'Alex Kim',
            'normalized_name' => 'alex kim',
            'external_entity_id' => 'kg:alex-kim-artist',
        ]);

        $summary = app(AuthorEntityConsolidator::class)->consolidate();

        $this->assertSame([
            'groups' => 0,
            'merged_authors' => 0,
            'skipped_groups' => 1,
        ], $summary);
        $this->assertDatabaseCount('authors', 2);
    }

    public function test_dry_run_reports_duplicates_without_changing_records(): void
    {
        Author::factory()->create([
            'name' => 'J.K. Rowling',
            'normalized_name' => 'jk rowling',
        ]);
        Author::factory()->create([
            'name' => 'JK Rowling',
            'normalized_name' => 'jk rowling',
        ]);

        $summary = app(AuthorEntityConsolidator::class)->consolidate(dryRun: true);

        $this->assertSame(1, $summary['groups']);
        $this->assertSame(1, $summary['merged_authors']);
        $this->assertSame(0, $summary['skipped_groups']);
        $this->assertDatabaseCount('authors', 2);
        $this->assertDatabaseCount('author_aliases', 0);
    }
}
