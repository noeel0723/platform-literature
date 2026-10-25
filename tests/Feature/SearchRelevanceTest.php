<?php

namespace Tests\Feature;

use App\Models\ApiSource;
use App\Models\Author;
use App\Models\AuthorAlias;
use App\Models\CanonicalWork;
use App\Models\Category;
use App\Models\Literature;
use App\Models\LiteratureMetadataOverride;
use App\Models\LiteratureSourceMapping;
use App\Models\ReadingList;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SearchRelevanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[TestWith(['literatures.index'])]
    #[TestWith(['search.index'])]
    #[TestWith(['literatures.latest'])]
    public function test_an_old_exact_match_is_first_even_with_more_than_one_hundred_newer_matches(string $route): void
    {
        Http::preventStrayRequests();
        $this->freezeTime();
        $source = ApiSource::factory()->create();
        $exact = Literature::factory()->for($source)->create([
            'title' => 'Dune', 'updated_at' => now()->subYear(),
            'publisher' => null, 'identifier' => null, 'publication_year' => null,
        ]);
        Literature::factory()->for($source)->count(105)->sequence(
            fn ($sequence) => ['title' => 'Dune Adventures '.$sequence->index],
        )->create(['updated_at' => now(), 'knowledge_graph_id' => 'kg:/complete']);

        $response = $this->get(route($route, ['q' => 'Dune', 'type' => 'novel']));

        $path = $route === 'literatures.latest' ? 'literatures.data' : 'literatures';
        $this->assertSame($exact->id, $response->inertiaProps($path.'.0.id'));
        Http::assertNothingSent();
    }

    public function test_exact_alternate_prefix_and_partial_titles_have_the_same_order_on_all_search_surfaces(): void
    {
        Http::preventStrayRequests();
        $exact = Literature::factory()->create(['title' => 'Dune']);
        $alternate = Literature::factory()->create(['title' => 'Dune', 'original_title' => 'Desert Planet']);
        $prefix = Literature::factory()->create(['title' => 'Dune Messiah']);
        $partial = Literature::factory()->create(['title' => 'Children of Dune']);
        $expected = [$exact->id, $alternate->id, $prefix->id, $partial->id];

        foreach (['literatures.index', 'search.index', 'literatures.latest'] as $route) {
            $response = $this->get(route($route, ['q' => 'Dune', 'type' => 'novel']));
            $path = $route === 'literatures.latest' ? 'literatures.data' : 'literatures';

            $this->assertSame($expected, collect($response->inertiaProps($path))->pluck('id')->all());
        }
        Http::assertNothingSent();
    }

    #[TestWith(['Frank Herbert'])]
    #[TestWith(['F. Herbert'])]
    public function test_author_names_and_aliases_outrank_category_only_matches(string $term): void
    {
        Http::preventStrayRequests();
        $author = Author::factory()->create(['name' => 'Frank Herbert']);
        AuthorAlias::factory()->for($author)->create(['name' => 'F. Herbert']);
        $book = Literature::factory()->create(['title' => 'Dune']);
        $book->authors()->attach($author);
        $categoryOnly = Literature::factory()->create(['title' => 'An unrelated book']);
        $categoryOnly->categories()->attach(Category::factory()->create(['name' => $term]));

        foreach (['literatures.index', 'search.index', 'literatures.latest'] as $route) {
            $response = $this->get(route($route, ['q' => $term, 'type' => 'novel']));
            $path = $route === 'literatures.latest' ? 'literatures.data' : 'literatures';

            $this->assertSame([$book->id, $categoryOnly->id], collect($response->inertiaProps($path))->pluck('id')->all());
        }
        Http::assertNothingSent();
    }

    public function test_an_exact_sibling_title_ranks_the_preferred_canonical_record_without_duplicates(): void
    {
        Http::preventStrayRequests();
        $preferred = Literature::factory()->create(['title' => 'Desert Planet']);
        $sibling = Literature::factory()->create(['title' => 'Dune']);
        $work = $this->mapTogether($preferred, $sibling);
        Literature::factory()->create(['title' => 'Dune Messiah']);

        foreach (['literatures.index', 'search.index', 'literatures.latest'] as $route) {
            $response = $this->get(route($route, ['q' => 'Dune', 'type' => 'novel']));
            $path = $route === 'literatures.latest' ? 'literatures.data' : 'literatures';
            $ids = collect($response->inertiaProps($path))->pluck('id')->all();

            $this->assertCount(2, $ids);
            $this->assertSame($preferred->id, $ids[0]);
            $this->assertNotContains($sibling->id, $ids);
        }
        $this->assertSame($preferred->id, $work->fresh()->preferred_literature_id);
        Http::assertNothingSent();
    }

    public function test_a_curated_canonical_title_is_searchable_even_if_stored_on_a_different_source_record(): void
    {
        Http::preventStrayRequests();
        $preferred = Literature::factory()->create(['title' => 'Provider Title']);
        $sibling = Literature::factory()->create(['title' => 'Another Provider Title']);
        $work = $this->mapTogether($preferred, $sibling);
        LiteratureMetadataOverride::factory()->create([
            'canonical_work_id' => $work->id, 'literature_id' => $sibling->id,
            'title' => 'Curated Dune',
        ]);
        Literature::factory()->create(['title' => 'Curated Dune Adventures']);

        foreach (['literatures.index', 'search.index', 'literatures.latest'] as $route) {
            $response = $this->get(route($route, ['q' => 'Curated Dune', 'type' => 'novel']));
            $path = $route === 'literatures.latest' ? 'literatures.data' : 'literatures';

            $this->assertSame($preferred->id, $response->inertiaProps($path.'.0.id'));
            $this->assertSame('Curated Dune', $response->inertiaProps($path.'.0.title'));
        }
        Http::assertNothingSent();
    }

    public function test_metadata_completeness_breaks_equal_textual_matches_before_recency(): void
    {
        $complete = Literature::factory()->create(['title' => 'Dune', 'cover_url' => 'https://example.test/cover.jpg']);
        $complete->authors()->attach(Author::factory()->create());
        $sparse = Literature::factory()->create([
            'title' => 'Dune', 'synopsis' => null, 'publisher' => null,
            'identifier' => null, 'publication_year' => null,
        ]);

        $response = $this->get(route('literatures.latest', ['q' => 'Dune']));

        $this->assertSame([$complete->id, $sparse->id], collect($response->inertiaProps('literatures.data'))->pluck('id')->all());
    }

    public function test_popularity_includes_readers_of_sibling_sources_but_cannot_beat_an_exact_title(): void
    {
        $exact = Literature::factory()->create(['title' => 'Dune']);
        $popular = Literature::factory()->create(['title' => 'Dune Messiah']);
        $sibling = Literature::factory()->create(['title' => 'Desert Prophet']);
        $this->mapTogether($popular, $sibling);
        $unpopular = Literature::factory()->create(['title' => 'Dune Heretics']);
        User::factory()->count(4)->create()->each(function (User $user) use ($sibling): void {
            ReadingList::factory()->for($user)->for($sibling)->create(['status' => 'completed']);
        });

        $response = $this->get(route('literatures.latest', ['q' => 'Dune']));

        $this->assertSame([$exact->id, $popular->id, $unpopular->id], collect($response->inertiaProps('literatures.data'))->pluck('id')->all());
    }

    public function test_pagination_counts_unique_works_keeps_filters_and_has_no_overlapping_results(): void
    {
        $source = ApiSource::factory()->create();
        $books = Literature::factory()->for($source)->count(21)->sequence(
            fn ($sequence) => ['title' => 'Dune Story '.$sequence->index],
        )->create();
        $sibling = Literature::factory()->for($source)->create(['title' => 'Dune Extra Source']);
        $this->mapTogether($books->first(), $sibling);
        Literature::factory()->for($source)->create(['title' => 'Dune', 'type' => 'manga']);

        $first = $this->get(route('literatures.latest', ['q' => 'Dune', 'type' => 'novel']));
        $second = $this->get(route('literatures.latest', ['q' => 'Dune', 'type' => 'novel', 'page' => 2]));

        $firstIds = collect($first->inertiaProps('literatures.data'))->pluck('id');
        $secondIds = collect($second->inertiaProps('literatures.data'))->pluck('id');
        $this->assertSame(21, $first->inertiaProps('literatures.total'));
        $this->assertCount(18, $firstIds);
        $this->assertCount(3, $secondIds);
        $this->assertSame([], $firstIds->intersect($secondIds)->all());
        $this->assertEqualsCanonicalizing($books->pluck('id')->all(), $firstIds->merge($secondIds)->all());
        $this->assertStringContainsString('q=Dune', $first->inertiaProps('literatures.next_page_url'));
        $this->assertStringContainsString('type=novel', $first->inertiaProps('literatures.next_page_url'));
    }

    public function test_catalog_hydrates_only_the_display_window_and_one_expansion_probe(): void
    {
        Http::preventStrayRequests();
        $source = ApiSource::factory()->create();
        Literature::factory()->for($source)->count(110)->create(['title' => 'Dune']);
        $retrieved = 0;
        Event::listen('eloquent.retrieved: '.Literature::class, function () use (&$retrieved): void {
            $retrieved++;
        });

        $response = $this->get(route('literatures.index', ['q' => 'Dune', 'type' => 'novel']));

        $this->assertCount(4, $response->inertiaProps('literatures'));
        $this->assertTrue($response->inertiaProps('canExpand'));
        $this->assertLessThanOrEqual(5, $retrieved);
        Http::assertNothingSent();
    }

    #[TestWith(['100%'])]
    #[TestWith(['part_one'])]
    #[TestWith(["Reader's choice!"])]
    public function test_search_treats_wildcards_and_quotes_as_literal_bound_text(string $term): void
    {
        Http::preventStrayRequests();
        $literal = Literature::factory()->create(['title' => $term]);
        Literature::factory()->create(['title' => '1000 partXone Readers choice']);

        foreach (['literatures.index', 'search.index', 'literatures.latest'] as $route) {
            $response = $this->get(route($route, ['q' => $term, 'type' => 'novel']));
            $path = $route === 'literatures.latest' ? 'literatures.data' : 'literatures';

            $this->assertSame([$literal->id], collect($response->inertiaProps($path))->pluck('id')->all());
        }
        Http::assertNothingSent();
    }

    public function test_provider_timestamp_refreshes_do_not_reorder_equal_search_hits_between_pages(): void
    {
        $source = ApiSource::factory()->create();
        $books = Literature::factory()->for($source)->count(20)->create(['title' => 'Dune']);
        $first = $this->get(route('literatures.latest', ['q' => 'Dune']));
        $firstIds = collect($first->inertiaProps('literatures.data'))->pluck('id');
        $books->first()->update(['updated_at' => now()->addDay()]);

        $second = $this->get(route('literatures.latest', ['q' => 'Dune', 'page' => 2]));

        $secondIds = collect($second->inertiaProps('literatures.data'))->pluck('id');
        $this->assertCount(2, $secondIds);
        $this->assertSame([], $firstIds->intersect($secondIds)->all());
        $this->assertEqualsCanonicalizing($books->pluck('id')->all(), $firstIds->merge($secondIds)->all());
    }

    private function mapTogether(Literature $preferred, Literature $sibling): CanonicalWork
    {
        $work = CanonicalWork::factory()->create([
            'canonical_title' => $preferred->title,
            'type' => $preferred->type,
            'preferred_literature_id' => $preferred->id,
        ]);

        foreach ([$preferred, $sibling] as $literature) {
            LiteratureSourceMapping::factory()->for($work, 'canonicalWork')->for($literature)->create();
        }

        return $work;
    }
}
