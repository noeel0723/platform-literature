<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\CanonicalWork;
use App\Models\Category;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Models\ReadingList;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LiteratureBrowseTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_global_literature_pages_only_read_local_literature(): void
    {
        Http::preventStrayRequests();
        Literature::factory()->create(['title' => 'Already Synced Locally']);

        $this->get(route('literature.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Literature/Index')
                ->where('popularLiteratures.0.title', 'Already Synced Locally'));
        $this->get(route('literature.browse'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Literature/Browse')
                ->where('literatures.total', 1)
                ->where('literatures.data.0.title', 'Already Synced Locally'));
    }

    public function test_browse_shows_one_representative_for_each_canonical_work_and_keeps_legacy_literature(): void
    {
        $preferred = Literature::factory()->create(['title' => 'Dune']);
        $alternate = Literature::factory()->create(['title' => 'Dune Alternate Source']);
        $legacy = Literature::factory()->create(['title' => 'Legacy Local Work']);
        $this->canonicalize($preferred, $alternate);

        $response = $this->get(route('literature.browse'));

        $this->assertSame(2, $response->inertiaProps('literatures.total'));
        $this->assertEqualsCanonicalizing(
            ['Dune', 'Legacy Local Work'],
            collect($response->inertiaProps('literatures.data'))->pluck('title')->all(),
        );
    }

    public function test_canonical_metrics_count_each_readers_duplicate_source_records_once(): void
    {
        $user = User::factory()->create();
        $preferred = Literature::factory()->create(['title' => 'One Canonical Work']);
        $alternate = Literature::factory()->create(['title' => 'One Canonical Work Alternate']);
        $this->canonicalize($preferred, $alternate);

        foreach ([$preferred, $alternate] as $index => $literature) {
            ReadingList::factory()->for($user)->for($literature)->create(['status' => 'reading']);
            Review::factory()->for($user)->for($literature)->create(['rating' => $index === 0 ? 5 : 3]);
            Activity::factory()->for($user)->for($literature)->create(['type' => Activity::TYPE_STARTED_READING]);
            $user->favoriteLiteratures()->attach($literature, ['position' => $index]);
        }

        $response = $this->get(route('literature.browse'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('literatures.total', 1)
            ->where('literatures.data.0.average_rating', 4)
            ->where('literatures.data.0.rating_count', 1)
            ->where('literatures.data.0.weekly_popularity_score', 4)
            ->where('literatures.data.0.overall_popularity_score', 7));
    }

    public function test_popular_literature_uses_recent_activity_from_the_whole_community(): void
    {
        $popular = Literature::factory()->create(['title' => 'Community Favorite']);
        $quiet = Literature::factory()->create(['title' => 'Quiet Work']);

        Activity::factory()->for(User::factory())->for($popular)->create(['type' => Activity::TYPE_STARTED_READING]);
        Activity::factory()->for(User::factory())->for($popular)->create(['type' => Activity::TYPE_STARTED_READING]);
        Activity::factory()->for(User::factory())->for($quiet)->create(['type' => Activity::TYPE_STARTED_READING]);

        $response = $this->get(route('literature.index'));

        $this->assertSame('Community Favorite', $response->inertiaProps('popularLiteratures.0.title'));
        $this->assertGreaterThan(
            $response->inertiaProps('popularLiteratures.1.weekly_popularity_score'),
            $response->inertiaProps('popularLiteratures.0.weekly_popularity_score'),
        );
    }

    public function test_genre_filter_uses_categories_from_canonical_siblings(): void
    {
        $horror = Category::factory()->create(['name' => 'Horror', 'slug' => 'horror']);
        $preferred = Literature::factory()->create(['title' => 'Canonical Horror']);
        $alternate = Literature::factory()->create(['title' => 'Canonical Horror Alternate']);
        $other = Literature::factory()->create(['title' => 'Gentle Drama']);
        $alternate->categories()->attach($horror);
        $this->canonicalize($preferred, $alternate);

        $response = $this->get(route('literature.browse', ['genre' => 'horror']));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('filters.genre', 'horror')
            ->where('literatures.total', 1)
            ->where('literatures.data.0.title', 'Canonical Horror'));
        $this->assertNotContains($other->title, collect($response->inertiaProps('literatures.data'))->pluck('title'));
    }

    public function test_decade_and_minimum_visible_rating_filters_can_be_combined(): void
    {
        $match = Literature::factory()->create(['title' => 'Rated 2010s', 'publication_year' => 2016]);
        $old = Literature::factory()->create(['title' => 'Rated 2000s', 'publication_year' => 2006]);
        $low = Literature::factory()->create(['title' => 'Low Rated 2010s', 'publication_year' => 2017]);
        Review::factory()->for($match)->create(['rating' => 4.5]);
        Review::factory()->for($old)->create(['rating' => 4.5]);
        Review::factory()->for($low)->create(['rating' => 3.5]);
        Review::factory()->for($low)->create(['rating' => 5, 'hidden_at' => now()]);

        $response = $this->get(route('literature.browse', [
            'decade' => 2010,
            'rating' => 4,
        ]));

        $this->assertSame(['Rated 2010s'], collect($response->inertiaProps('literatures.data'))->pluck('title')->all());
    }

    public function test_release_date_sorting_supports_newest_and_oldest_first(): void
    {
        Literature::factory()->create(['title' => 'Middle', 'publication_year' => 2000]);
        Literature::factory()->create(['title' => 'Newest', 'publication_year' => 2025]);
        Literature::factory()->create(['title' => 'Oldest', 'publication_year' => 1950]);

        $newest = $this->get(route('literature.browse', ['sort' => 'year-desc']));
        $oldest = $this->get(route('literature.browse', ['sort' => 'year-asc']));

        $this->assertSame(['Newest', 'Middle', 'Oldest'], collect($newest->inertiaProps('literatures.data'))->pluck('title')->all());
        $this->assertSame(['Oldest', 'Middle', 'Newest'], collect($oldest->inertiaProps('literatures.data'))->pluck('title')->all());
    }

    public function test_average_rating_sorting_supports_highest_and_lowest_first(): void
    {
        $high = Literature::factory()->create(['title' => 'Highest Rated']);
        $low = Literature::factory()->create(['title' => 'Lowest Rated']);
        Review::factory()->for($high)->create(['rating' => 5]);
        Review::factory()->for($low)->create(['rating' => 2]);

        $highest = $this->get(route('literature.browse', ['sort' => 'rating-desc']));
        $lowest = $this->get(route('literature.browse', ['sort' => 'rating-asc']));

        $this->assertSame(['Highest Rated', 'Lowest Rated'], collect($highest->inertiaProps('literatures.data'))->pluck('title')->all());
        $this->assertSame(['Lowest Rated', 'Highest Rated'], collect($lowest->inertiaProps('literatures.data'))->pluck('title')->all());
    }

    public function test_popularity_sort_uses_recent_activity_before_overall_fallback(): void
    {
        $recent = Literature::factory()->create(['title' => 'Recent Interest']);
        $overall = Literature::factory()->create(['title' => 'Overall Interest']);
        Activity::factory()->for(User::factory())->for($recent)->create(['type' => Activity::TYPE_STARTED_READING]);
        ReadingList::factory()->count(3)->for($overall)->create(['status' => 'reading']);

        $response = $this->get(route('literature.browse', ['sort' => 'popularity']));

        $this->assertSame(['Recent Interest', 'Overall Interest'], collect($response->inertiaProps('literatures.data'))->pluck('title')->all());
    }

    public function test_genre_decade_rating_and_sort_filters_work_together(): void
    {
        $fantasy = Category::factory()->create(['name' => 'Fantasy', 'slug' => 'fantasy']);
        $higher = Literature::factory()->create(['title' => 'Higher Fantasy', 'publication_year' => 2019]);
        $lower = Literature::factory()->create(['title' => 'Lower Fantasy', 'publication_year' => 2012]);
        $outside = Literature::factory()->create(['title' => 'Older Fantasy', 'publication_year' => 2009]);
        foreach ([$higher, $lower, $outside] as $literature) {
            $literature->categories()->attach($fantasy);
        }
        Review::factory()->for($higher)->create(['rating' => 5]);
        Review::factory()->for($lower)->create(['rating' => 4]);
        Review::factory()->for($outside)->create(['rating' => 5]);

        $response = $this->get(route('literature.browse', [
            'genre' => 'fantasy',
            'decade' => 2010,
            'rating' => 4,
            'sort' => 'rating-desc',
        ]));

        $this->assertSame(['Higher Fantasy', 'Lower Fantasy'], collect($response->inertiaProps('literatures.data'))->pluck('title')->all());
    }

    public function test_pagination_preserves_all_browse_query_parameters(): void
    {
        $horror = Category::factory()->create(['name' => 'Horror', 'slug' => 'horror']);
        Literature::factory()->count(49)->create(['publication_year' => 2015])->each(function (Literature $literature) use ($horror): void {
            $literature->categories()->attach($horror);
            Review::factory()->for($literature)->create(['rating' => 4]);
        });

        $response = $this->get(route('literature.browse', [
            'genre' => 'horror',
            'decade' => 2010,
            'rating' => 4,
            'sort' => 'year-desc',
        ]));
        $next = $response->inertiaProps('literatures.next_page_url');

        $this->assertStringContainsString('genre=horror', $next);
        $this->assertStringContainsString('decade=2010', $next);
        $this->assertStringContainsString('rating=4', $next);
        $this->assertStringContainsString('sort=year-desc', $next);
    }

    public function test_literature_detail_genre_links_to_global_browse_for_guests(): void
    {
        $horror = Category::factory()->create(['name' => 'Horror', 'slug' => 'horror']);
        $literature = Literature::factory()->create(['title' => 'Linked Horror']);
        $literature->categories()->attach($horror);

        $this->get(route('literatures.show', $literature))
            ->assertInertia(fn (Assert $page) => $page
                ->where('literature.genre_links.0.url', route('literature.browse', ['genre' => 'horror'])));
    }

    public function test_profile_literature_remains_the_users_completed_collection(): void
    {
        $user = User::factory()->create(['username' => 'profile_collection_reader']);
        $completed = Literature::factory()->create(['title' => 'Completed By Reader']);
        $globalOnly = Literature::factory()->create(['title' => 'Global Only']);
        ReadingList::factory()->for($user)->for($completed)->create([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $response = $this->get(route('profiles.literature', $user));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Literature')
            ->where('completedLiterature.total', 1)
            ->where('completedLiterature.data.0.literature.title', 'Completed By Reader'));
        $this->assertNotContains($globalOnly->title, collect($response->inertiaProps('completedLiterature.data'))->pluck('literature.title'));
    }

    private function canonicalize(Literature $preferred, Literature $alternate): CanonicalWork
    {
        $work = CanonicalWork::factory()->create([
            'preferred_literature_id' => $preferred->id,
            'canonical_title' => $preferred->title,
            'normalized_title' => str($preferred->title)->lower()->toString(),
            'type' => $preferred->type,
            'publication_year' => $preferred->publication_year,
        ]);

        foreach ([$preferred, $alternate] as $index => $literature) {
            LiteratureSourceMapping::factory()
                ->for($work)
                ->for($literature)
                ->for($literature->apiSource)
                ->create(['source_external_id' => 'browse-source-'.$index.'-'.$literature->id]);
        }

        return $work;
    }
}
