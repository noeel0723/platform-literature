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

    public function test_source_ranking_selects_one_preferred_record_without_deleting_provenance(): void
    {
        $author = Author::factory()->create(['name' => 'Haruichi Furudate']);
        $work = CanonicalWork::factory()->for($author, 'primaryAuthor')->create([
            'canonical_title' => 'Haikyu!!',
            'normalized_title' => 'haikyu',
            'type' => 'manga',
            'publication_year' => 2012,
        ]);
        $aniList = ApiSource::factory()->create(['key' => 'anilist', 'name' => 'AniList']);
        $mangaDex = ApiSource::factory()->create(['key' => 'mangadex', 'name' => 'MangaDex']);
        $aniListLiterature = Literature::factory()->for($aniList)->create([
            'title' => 'Haikyu!!',
            'type' => 'manga',
            'cover_url' => 'https://images.example.test/anilist-haikyu.jpg',
        ]);
        $mangaDexLiterature = Literature::factory()->for($mangaDex)->create([
            'title' => 'Haikyuu',
            'type' => 'manga',
            'cover_url' => 'https://images.example.test/mangadex-haikyu.jpg',
        ]);
        $aniListLiterature->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        $mangaDexLiterature->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        $this->map($work, $aniListLiterature);
        $this->map($work, $mangaDexLiterature);

        $preferred = app(CanonicalLiteratureProjector::class)->refresh($work);

        $this->assertTrue($preferred->is($aniListLiterature));
        $this->assertDatabaseCount('literatures', 2);
        $this->assertDatabaseCount('literature_source_mappings', 2);
        $this->assertDatabaseHas('canonical_works', [
            'id' => $work->id,
            'preferred_literature_id' => $aniListLiterature->id,
        ]);
        $this->assertGreaterThan(
            $mangaDexLiterature->sourceMapping()->value('quality_score'),
            $aniListLiterature->sourceMapping()->value('quality_score'),
        );
    }

    public function test_searching_an_alternate_source_title_returns_only_the_preferred_literature(): void
    {
        [$work, $preferred, $alternate] = $this->createProjectedPair();

        $response = $this->get(route('search.index', ['q' => 'Haikyuu']));

        $response->assertOk()->assertSeeText('Haikyu!!');
        $this->assertSame(1, substr_count($response->getContent(), 'data-search-literature'));
        $this->assertTrue($work->fresh()->preferredLiterature->is($preferred));
        $this->assertFalse($work->fresh()->preferredLiterature->is($alternate));
    }

    public function test_a_substantially_more_complete_fallback_can_become_the_preferred_record(): void
    {
        $author = Author::factory()->create();
        $work = CanonicalWork::factory()->for($author, 'primaryAuthor')->create(['type' => 'manga']);
        $aniList = ApiSource::factory()->create(['key' => 'anilist']);
        $mangaDex = ApiSource::factory()->create(['key' => 'mangadex']);
        $incompletePrimary = Literature::factory()->for($aniList)->create([
            'type' => 'manga',
            'identifier' => null,
            'cover_url' => null,
            'synopsis' => null,
            'publisher' => null,
            'publication_year' => null,
        ]);
        $completeFallback = Literature::factory()->for($mangaDex)->create([
            'type' => 'manga',
            'cover_url' => 'https://images.example.test/complete.jpg',
        ]);
        $completeFallback->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        $this->map($work, $incompletePrimary);
        $this->map($work, $completeFallback);

        $preferred = app(CanonicalLiteratureProjector::class)->refresh($work);

        $this->assertTrue($preferred->is($completeFallback));
    }

    public function test_a_large_open_library_cover_beats_a_google_thumbnail_for_the_same_novel(): void
    {
        $author = Author::factory()->create(['name' => 'C. S. Lewis']);
        $work = CanonicalWork::factory()->for($author, 'primaryAuthor')->create([
            'canonical_title' => 'The Silver Chair',
            'normalized_title' => 'the silver chair',
            'type' => 'novel',
            'publication_year' => 1953,
        ]);
        $googleBooks = ApiSource::factory()->create(['key' => 'google-books']);
        $openLibrary = ApiSource::factory()->create(['key' => 'open-library']);
        $thumbnail = Literature::factory()->for($googleBooks)->create([
            'title' => 'The Silver Chair',
            'type' => 'novel',
            'cover_url' => 'https://books.google.com/books/content?id=abc&zoom=1',
        ]);
        $largeCover = Literature::factory()->for($openLibrary)->create([
            'title' => 'The Silver Chair',
            'type' => 'novel',
            'cover_url' => 'https://covers.openlibrary.org/b/id/14325438-L.jpg?default=false',
        ]);
        $thumbnail->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        $largeCover->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        $this->map($work, $thumbnail);
        $this->map($work, $largeCover);

        $preferred = app(CanonicalLiteratureProjector::class)->refresh($work);

        $this->assertTrue($preferred->is($largeCover));
    }

    public function test_manual_curation_then_hardcover_are_the_preferred_novel_sources(): void
    {
        $author = Author::factory()->create(['name' => 'Andrea Hirata']);
        $work = CanonicalWork::factory()->for($author, 'primaryAuthor')->create([
            'canonical_title' => 'Laskar Pelangi',
            'normalized_title' => 'laskar pelangi',
            'type' => 'novel',
            'publication_year' => 2005,
        ]);
        $googleBooks = ApiSource::factory()->create(['key' => 'google-books']);
        $openLibrary = ApiSource::factory()->create(['key' => 'open-library']);
        $hardcover = ApiSource::factory()->create(['key' => 'hardcover']);
        $manual = ApiSource::factory()->create(['key' => 'manual-curated']);
        $records = collect([
            'google-books' => Literature::factory()->for($googleBooks)->create(['title' => 'Laskar Pelangi']),
            'open-library' => Literature::factory()->for($openLibrary)->create(['title' => 'Laskar Pelangi']),
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

        $this->assertTrue($preferredWithCuration->is($records->get('manual-curated')));
        $this->assertTrue($preferredWithoutCuration->is($records->get('hardcover')));
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

        $firstPage
            ->assertOk()
            ->assertSeeText('15 matches · 15 per page')
            ->assertSeeText('Page 1 of 1');
        $this->assertSame(15, substr_count($firstPage->getContent(), 'data-literature-card-size="compact"'));
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
            ->assertJsonPath('data.0.review_url', route('reviews.update', $alternate))
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
        $mangaDex = ApiSource::factory()->create(['key' => 'mangadex', 'name' => 'MangaDex']);
        $preferred = Literature::factory()->for($aniList)->create([
            'title' => 'Haikyu!!',
            'slug' => 'haikyu-anilist',
            'type' => 'manga',
            'cover_url' => 'https://images.example.test/anilist-haikyu.jpg',
        ]);
        $alternate = Literature::factory()->for($mangaDex)->create([
            'title' => 'Haikyuu',
            'slug' => 'haikyuu-mangadex',
            'type' => 'manga',
            'cover_url' => 'https://images.example.test/mangadex-haikyu.jpg',
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
