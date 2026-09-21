<?php

namespace Tests\Feature\Services\Literature;

use App\Models\ApiSource;
use App\Models\Author;
use App\Models\CanonicalWork;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Models\Review;
use App\Models\User;
use App\Services\Literature\CanonicalLiteratureProjector;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CanonicalLiteratureProjectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_anilist_is_preferred_over_kitsu_without_deleting_provenance(): void
    {
        $author = Author::factory()->create(['name' => 'Haruichi Furudate']);
        $work = CanonicalWork::factory()->for($author, 'primaryAuthor')->create([
            'canonical_title' => 'Haikyu!!',
            'normalized_title' => 'haikyu',
            'type' => 'manga',
            'publication_year' => 2012,
        ]);
        $aniList = ApiSource::factory()->create(['key' => 'anilist', 'name' => 'AniList']);
        $kitsu = ApiSource::factory()->create(['key' => 'kitsu', 'name' => 'Kitsu']);
        $aniListLiterature = Literature::factory()->for($aniList)->create([
            'title' => 'Haikyu!!',
            'type' => 'manga',
            'cover_url' => 'https://images.example.test/anilist-haikyu.jpg',
        ]);
        $kitsuLiterature = Literature::factory()->for($kitsu)->create([
            'title' => 'Haikyuu',
            'type' => 'manga',
            'cover_url' => 'https://images.example.test/kitsu-haikyu.jpg',
        ]);
        $aniListLiterature->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        $kitsuLiterature->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        $this->map($work, $aniListLiterature);
        $this->map($work, $kitsuLiterature);

        $preferred = app(CanonicalLiteratureProjector::class)->refresh($work);

        $this->assertTrue($preferred->is($aniListLiterature));
        $this->assertDatabaseCount('literatures', 2);
        $this->assertDatabaseCount('literature_source_mappings', 2);
        $this->assertDatabaseHas('canonical_works', [
            'id' => $work->id,
            'preferred_literature_id' => $aniListLiterature->id,
        ]);
        $this->assertGreaterThan(
            $kitsuLiterature->sourceMapping()->value('quality_score'),
            $aniListLiterature->sourceMapping()->value('quality_score'),
        );
    }

    public function test_searching_an_alternate_source_title_returns_only_the_preferred_literature(): void
    {
        [$work, $preferred, $alternate] = $this->createProjectedPair();

        $response = $this->get(route('search.index', ['q' => 'Haikyuu']));

        $response->assertOk();
        $this->assertCount(1, $response->inertiaProps('literatures'));
        $this->assertSame('Haikyu!!', $response->inertiaProps('literatures.0.title'));
        $this->assertTrue($work->fresh()->preferredLiterature->is($preferred));
        $this->assertFalse($work->fresh()->preferredLiterature->is($alternate));
    }

    public function test_a_substantially_more_complete_fallback_can_become_the_preferred_record(): void
    {
        $author = Author::factory()->create();
        $work = CanonicalWork::factory()->for($author, 'primaryAuthor')->create(['type' => 'manga']);
        $aniList = ApiSource::factory()->create(['key' => 'anilist']);
        $kitsu = ApiSource::factory()->create(['key' => 'kitsu']);
        $incompletePrimary = Literature::factory()->for($aniList)->create([
            'type' => 'manga',
            'identifier' => null,
            'cover_url' => null,
            'synopsis' => null,
            'publisher' => null,
            'publication_year' => null,
        ]);
        $completeFallback = Literature::factory()->for($kitsu)->create([
            'type' => 'manga',
            'cover_url' => 'https://images.example.test/complete.jpg',
        ]);
        $completeFallback->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        $this->map($work, $incompletePrimary);
        $this->map($work, $completeFallback);

        $preferred = app(CanonicalLiteratureProjector::class)->refresh($work);

        $this->assertTrue($preferred->is($completeFallback));
    }

    public function test_a_preferred_hardcover_record_inherits_a_google_books_cover_with_provenance(): void
    {
        $author = Author::factory()->create(['name' => 'C. S. Lewis']);
        $work = CanonicalWork::factory()->for($author, 'primaryAuthor')->create([
            'canonical_title' => 'The Silver Chair',
            'normalized_title' => 'the silver chair',
            'type' => 'novel',
            'publication_year' => 1953,
        ]);
        $hardcover = ApiSource::factory()->create(['key' => 'hardcover']);
        $googleBooks = ApiSource::factory()->create(['key' => 'google-books']);
        $primary = Literature::factory()->for($hardcover)->create([
            'title' => 'The Silver Chair',
            'type' => 'novel',
            'identifier' => '9780064471091',
            'cover_url' => null,
            'synopsis' => 'A complete Hardcover synopsis keeps this the preferred source record.',
            'publisher' => 'HarperCollins',
            'publication_year' => 1953,
        ]);
        $fallback = Literature::factory()->for($googleBooks)->create([
            'title' => 'The Silver Chair',
            'type' => 'novel',
            'identifier' => null,
            'cover_url' => 'https://books.google.com/books/content?id=silver-chair&zoom=6&w=1000',
            'backdrop_url' => 'https://images.example.test/silver-chair-artwork.jpg',
            'synopsis' => null,
            'publisher' => null,
            'publication_year' => 1953,
        ]);
        $primary->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        $fallback->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        $primaryMapping = $this->map($work, $primary);
        $this->map($work, $fallback);

        $preferred = app(CanonicalLiteratureProjector::class)->refresh($work);

        $this->assertTrue($preferred->is($primary));
        $this->assertSame($fallback->cover_url, $primary->fresh()->cover_url);
        $this->assertSame($fallback->backdrop_url, $primary->fresh()->backdrop_url);
        $this->assertSame('google-books', $primaryMapping->fresh()->field_provenance['cover_url']);
        $this->assertSame('google-books', $primaryMapping->fresh()->field_provenance['backdrop_url']);

        $primaryMapping->update(['field_provenance' => ['cover_url' => 'hardcover']]);
        app(CanonicalLiteratureProjector::class)->refresh($work->fresh());

        $this->assertSame('google-books', $primaryMapping->fresh()->field_provenance['cover_url']);
    }

    public function test_manual_curation_then_hardcover_then_google_books_are_the_preferred_novel_sources(): void
    {
        $author = Author::factory()->create(['name' => 'Andrea Hirata']);
        $work = CanonicalWork::factory()->for($author, 'primaryAuthor')->create([
            'canonical_title' => 'Laskar Pelangi',
            'normalized_title' => 'laskar pelangi',
            'type' => 'novel',
            'publication_year' => 2005,
        ]);
        $googleBooks = ApiSource::factory()->create(['key' => 'google-books']);
        $hardcover = ApiSource::factory()->create(['key' => 'hardcover']);
        $manual = ApiSource::factory()->create(['key' => 'manual-curated']);
        $records = collect([
            'google-books' => Literature::factory()->for($googleBooks)->create(['title' => 'Laskar Pelangi']),
            'hardcover' => Literature::factory()->for($hardcover)->create(['title' => 'Laskar Pelangi']),
            'manual-curated' => Literature::factory()->for($manual)->create(['title' => 'Laskar Pelangi']),
        ]);
        $records->each(function (Literature $literature) use ($author, $work): void {
            $literature->authors()->attach($author, ['role' => 'author', 'position' => 0]);
            $this->map($work, $literature);
        });

        $preferredWithCuration = app(CanonicalLiteratureProjector::class)->refresh($work);
        $records->get('manual-curated')->sourceMapping()->delete();
        $preferredWithoutCuration = app(CanonicalLiteratureProjector::class)->refresh($work->fresh());
        $records->get('hardcover')->sourceMapping()->delete();
        $preferredWithGoogleBooksOnly = app(CanonicalLiteratureProjector::class)->refresh($work->fresh());

        $this->assertTrue($preferredWithCuration->is($records->get('manual-curated')));
        $this->assertTrue($preferredWithoutCuration->is($records->get('hardcover')));
        $this->assertTrue($preferredWithGoogleBooksOnly->is($records->get('google-books')));
    }

    public function test_comic_vine_is_the_preferred_western_comic_source(): void
    {
        $author = Author::factory()->create();
        $work = CanonicalWork::factory()->for($author, 'primaryAuthor')->create(['type' => 'western-comic']);
        $comicVine = ApiSource::factory()->create(['key' => 'comic-vine']);
        $legacySource = ApiSource::factory()->create(['key' => 'legacy-comics']);
        $comicVineLiterature = Literature::factory()->for($comicVine)->create([
            'title' => 'Kingdom Come',
            'type' => 'western-comic',
        ]);
        $legacyLiterature = Literature::factory()->for($legacySource)->create([
            'title' => 'Kingdom Come',
            'type' => 'western-comic',
        ]);
        $comicVineLiterature->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        $legacyLiterature->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        $this->map($work, $comicVineLiterature);
        $this->map($work, $legacyLiterature);

        $preferred = app(CanonicalLiteratureProjector::class)->refresh($work);

        $this->assertTrue($preferred->is($comicVineLiterature));
    }

    public function test_unknown_legacy_source_uses_the_default_priority_without_an_exception(): void
    {
        $author = Author::factory()->create();
        $work = CanonicalWork::factory()->for($author, 'primaryAuthor')->create(['type' => 'novel']);
        $googleBooks = ApiSource::factory()->create(['key' => 'google-books']);
        $legacySource = ApiSource::factory()->create(['key' => 'legacy-books']);
        $googleBooksLiterature = Literature::factory()->for($googleBooks)->create(['title' => 'The Same Edition']);
        $legacyLiterature = Literature::factory()->for($legacySource)->create(['title' => 'The Same Edition']);
        $googleBooksLiterature->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        $legacyLiterature->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        $googleBooksMapping = $this->map($work, $googleBooksLiterature);
        $legacyMapping = $this->map($work, $legacyLiterature);

        $preferred = app(CanonicalLiteratureProjector::class)->refresh($work);

        $this->assertTrue($preferred->is($googleBooksLiterature));
        $this->assertSame(30, $googleBooksMapping->fresh()->quality_score - $legacyMapping->fresh()->quality_score);
    }

    public function test_latest_catalog_paginates_canonical_works_and_keeps_unmapped_legacy_records(): void
    {
        $this->createProjectedPair();

        foreach (range(1, 14) as $position) {
            Literature::factory()->create([
                'title' => "Haikyuu Legacy {$position}",
                'slug' => "haikyuu-legacy-{$position}",
                'type' => 'manga',
            ]);
        }

        $firstPage = $this->get(route('literatures.latest', [
            'q' => 'Haikyuu',
            'type' => 'manga',
        ]));

        $this->assertSame(15, $firstPage->inertiaProps('literatures.total'));
        $this->assertSame(18, $firstPage->inertiaProps('literatures.per_page'));
        $this->assertSame(1, $firstPage->inertiaProps('literatures.current_page'));
        $this->assertSame(1, $firstPage->inertiaProps('literatures.last_page'));
        $this->assertCount(15, $firstPage->inertiaProps('literatures.data'));
    }

    public function test_quick_log_uses_one_canonical_result_and_finds_a_review_on_an_alternate_record(): void
    {
        $user = User::factory()->create();
        [, $preferred, $alternate] = $this->createProjectedPair();
        $review = Review::factory()->for($user)->for($alternate)->create([
            'rating' => 4.5,
            'body' => 'Energetic and precise.',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('quick-log.literatures', ['q' => 'Haikyuu']));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $preferred->id)
            ->assertJsonPath('data.0.title', 'Haikyu!!')
            ->assertJsonPath('data.0.review_url', route('reviews.update', $preferred))
            ->assertJsonPath('data.0.review.rating', 4.5)
            ->assertJsonPath('data.0.review.body', $review->body);
    }

    /** @return array{CanonicalWork, Literature, Literature} */
    private function createProjectedPair(): array
    {
        $author = Author::factory()->create(['name' => 'Haruichi Furudate']);
        $work = CanonicalWork::factory()->for($author, 'primaryAuthor')->create([
            'canonical_title' => 'Haikyu!!',
            'normalized_title' => 'haikyu',
            'type' => 'manga',
            'publication_year' => 2012,
        ]);
        $aniList = ApiSource::factory()->create(['key' => 'anilist', 'name' => 'AniList']);
        $kitsu = ApiSource::factory()->create(['key' => 'kitsu', 'name' => 'Kitsu']);
        $preferred = Literature::factory()->for($aniList)->create([
            'title' => 'Haikyu!!',
            'slug' => 'haikyu-anilist',
            'type' => 'manga',
            'cover_url' => 'https://images.example.test/anilist-haikyu.jpg',
        ]);
        $alternate = Literature::factory()->for($kitsu)->create([
            'title' => 'Haikyuu',
            'slug' => 'haikyuu-kitsu',
            'type' => 'manga',
            'cover_url' => 'https://images.example.test/kitsu-haikyu.jpg',
        ]);
        $preferred->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        $alternate->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        $this->map($work, $preferred);
        $this->map($work, $alternate);
        app(CanonicalLiteratureProjector::class)->refresh($work);

        return [$work, $preferred, $alternate];
    }

    private function map(CanonicalWork $work, Literature $literature): LiteratureSourceMapping
    {
        return LiteratureSourceMapping::factory()
            ->for($work, 'canonicalWork')
            ->for($literature)
            ->for($literature->apiSource, 'apiSource')
            ->create([
                'source_external_id' => $literature->external_id,
                'mapping_status' => LiteratureSourceMapping::STATUS_MATCHED,
                'confidence' => 0.95,
            ]);
    }
}
