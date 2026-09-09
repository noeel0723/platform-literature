<?php

namespace Tests\Feature\Services\Literature;

use App\Models\ApiSource;
use App\Models\Author;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Services\Literature\SemanticLiteratureResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SemanticLiteratureResolverTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_matching_isbn_maps_records_from_different_sources_to_one_canonical_work(): void
    {
        $author = Author::factory()->create([
            'name' => 'C. S. Lewis',
            'normalized_name' => 'cs lewis',
        ]);
        $googleBooks = $this->source('google-books');
        $openLibrary = $this->source('open-library');
        $first = $this->literature($googleBooks, 'google-narnia', 'The Lion, the Witch and the Wardrobe', 'ISBN-13: 9780064471046');
        $second = $this->literature($openLibrary, 'open-narnia', 'The Lion, the Witch and the Wardrobe', '978-0-06-447104-6');
        $this->attachAuthor($first, $author);
        $this->attachAuthor($second, $author);

        $firstMapping = app(SemanticLiteratureResolver::class)->resolve($first);
        $secondMapping = app(SemanticLiteratureResolver::class)->resolve($second);

        $this->assertSame($firstMapping->canonical_work_id, $secondMapping->canonical_work_id);
        $this->assertSame(LiteratureSourceMapping::STATUS_MATCHED, $secondMapping->mapping_status);
        $this->assertSame('external_identifier', $secondMapping->match_method);
        $this->assertDatabaseCount('canonical_works', 1);
        $this->assertDatabaseCount('literature_source_mappings', 2);
        $this->assertDatabaseHas('canonical_work_identifiers', [
            'canonical_work_id' => $firstMapping->canonical_work_id,
            'scheme' => 'isbn',
            'value' => '9780064471046',
        ]);
    }

    public function test_exact_normalized_title_author_type_and_year_can_match_without_shared_identifier(): void
    {
        $author = Author::factory()->create();
        $aniList = $this->source('anilist');
        $mangaDex = $this->source('mangadex');
        $first = $this->literature($aniList, '30013', 'One Piece', 'ANILIST:30013', 'manga', 1997);
        $second = $this->literature($mangaDex, 'a1b2', 'One-Piece', 'MANGADEX:a1b2', 'manga', 1997);
        $this->attachAuthor($first, $author);
        $this->attachAuthor($second, $author);

        $firstMapping = app(SemanticLiteratureResolver::class)->resolve($first);
        $secondMapping = app(SemanticLiteratureResolver::class)->resolve($second);

        $this->assertSame($firstMapping->canonical_work_id, $secondMapping->canonical_work_id);
        $this->assertSame('title_author_year', $secondMapping->match_method);
        $this->assertSame(0.92, $secondMapping->confidence);
        $this->assertDatabaseCount('canonical_works', 1);
    }

    public function test_novel_editions_with_the_same_title_and_author_can_match_across_publication_years(): void
    {
        $author = Author::factory()->create([
            'name' => 'C. S. Lewis',
            'normalized_name' => 'cs lewis',
        ]);
        $workRecord = $this->literature(
            $this->source('open-library'),
            'OL71078W',
            'The Silver Chair',
            'OPENLIBRARY:OL71078W',
            'novel',
            1953,
        );
        $editionRecord = $this->literature(
            $this->source('google-books'),
            'google-silver-chair',
            'The Silver Chair',
            '9780064471091',
            'novel',
            2002,
        );
        $this->attachAuthor($workRecord, $author);
        $this->attachAuthor($editionRecord, $author);

        $workMapping = app(SemanticLiteratureResolver::class)->resolve($workRecord);
        $editionMapping = app(SemanticLiteratureResolver::class)->resolve($editionRecord);

        $this->assertSame($workMapping->canonical_work_id, $editionMapping->canonical_work_id);
        $this->assertSame('title_author_edition', $editionMapping->match_method);
        $this->assertSame(LiteratureSourceMapping::STATUS_MATCHED, $editionMapping->mapping_status);
        $this->assertDatabaseCount('canonical_works', 1);
    }

    public function test_similar_records_with_different_authors_are_not_automatically_merged(): void
    {
        $firstAuthor = Author::factory()->create();
        $secondAuthor = Author::factory()->create();
        $first = $this->literature($this->source('source-one'), 'one', 'Home', null, 'novel', 2012);
        $second = $this->literature($this->source('source-two'), 'two', 'Home', null, 'novel', 2012);
        $this->attachAuthor($first, $firstAuthor);
        $this->attachAuthor($second, $secondAuthor);

        $firstMapping = app(SemanticLiteratureResolver::class)->resolve($first);
        $secondMapping = app(SemanticLiteratureResolver::class)->resolve($second);

        $this->assertNotSame($firstMapping->canonical_work_id, $secondMapping->canonical_work_id);
        $this->assertSame(LiteratureSourceMapping::STATUS_NEEDS_REVIEW, $secondMapping->mapping_status);
        $this->assertSame([$firstMapping->canonical_work_id], $secondMapping->candidate_work_ids);
        $this->assertDatabaseCount('canonical_works', 2);
    }

    public function test_missing_author_is_queued_for_review_instead_of_being_merged_by_title_only(): void
    {
        $author = Author::factory()->create();
        $first = $this->literature($this->source('source-one'), 'one', 'The Alchemist', null, 'novel', 1988);
        $second = $this->literature($this->source('source-two'), 'two', 'The Alchemist', null, 'novel', 1988);
        $this->attachAuthor($first, $author);

        $firstMapping = app(SemanticLiteratureResolver::class)->resolve($first);
        $secondMapping = app(SemanticLiteratureResolver::class)->resolve($second);

        $this->assertNotSame($firstMapping->canonical_work_id, $secondMapping->canonical_work_id);
        $this->assertSame('ambiguous_title', $secondMapping->match_method);
        $this->assertSame(LiteratureSourceMapping::STATUS_NEEDS_REVIEW, $secondMapping->mapping_status);
        $this->assertDatabaseCount('canonical_works', 2);
    }

    public function test_numeric_comic_vine_identity_is_not_misclassified_as_an_isbn(): void
    {
        $source = $this->source('comic-vine');
        $literature = $this->literature(
            $source,
            '123456',
            'Example Comic',
            'COMICVINE:4050-123456',
            'western-comic',
            2024,
        );

        app(SemanticLiteratureResolver::class)->resolve($literature);

        $this->assertDatabaseHas('canonical_work_identifiers', [
            'scheme' => 'comicvine',
            'value' => '4050-123456',
        ]);
        $this->assertDatabaseMissing('canonical_work_identifiers', [
            'scheme' => 'isbn',
            'source_key' => 'comic-vine',
        ]);
    }

    public function test_backfill_command_is_idempotent_and_preserves_source_provenance(): void
    {
        $source = $this->source('google-books');
        $literature = $this->literature($source, 'google-dune', 'Dune', '9780441172719', 'novel', 1965);

        $this->artisan('catalog:map-existing', ['--chunk' => 1])->assertSuccessful();
        $this->artisan('catalog:map-existing', ['--chunk' => 1])->assertSuccessful();
        $this->artisan('catalog:map-existing', ['--chunk' => 1, '--refresh' => true])->assertSuccessful();

        $this->assertDatabaseCount('canonical_works', 1);
        $this->assertDatabaseCount('literature_source_mappings', 1);
        $mapping = $literature->sourceMapping()->firstOrFail();
        $this->assertSame('google-books', $mapping->field_provenance['title']);
        $this->assertSame('google-books', $mapping->field_provenance['cover_url']);
    }

    private function source(string $key): ApiSource
    {
        return ApiSource::factory()->create([
            'key' => $key,
            'name' => str($key)->headline()->toString(),
        ]);
    }

    private function literature(
        ApiSource $source,
        string $externalId,
        string $title,
        ?string $identifier,
        string $type = 'novel',
        ?int $year = 1950,
    ): Literature {
        return Literature::factory()->for($source)->create([
            'external_id' => $externalId,
            'title' => $title,
            'identifier' => $identifier,
            'type' => $type,
            'publication_year' => $year,
            'cover_url' => "https://images.example.test/{$externalId}.jpg",
        ]);
    }

    private function attachAuthor(Literature $literature, Author $author): void
    {
        $literature->authors()->attach($author, ['role' => 'author', 'position' => 0]);
    }
}
