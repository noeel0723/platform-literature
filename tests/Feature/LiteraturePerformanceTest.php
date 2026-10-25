<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Author;
use App\Models\CanonicalWork;
use App\Models\Literature;
use App\Models\LiteratureMetadataOverride;
use App\Models\LiteratureRelation;
use App\Models\LiteratureSourceMapping;
use App\Models\ReadingList;
use App\Models\Review;
use App\Models\User;
use App\Services\Literature\LiteratureBrowseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LiteraturePerformanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reviews_are_paginated_without_hydrating_all_rows_or_changing_the_global_rating_summary(): void
    {
        $this->freezeTime();
        $literature = Literature::factory()->create();
        $reviews = Review::factory()->count(120)->for($literature)->sequence(
            fn ($sequence) => ['rating' => 4, 'created_at' => now()->subMinutes($sequence->index)],
        )->create();
        $hydrated = 0;
        Event::listen('eloquent.retrieved: '.Review::class, function () use (&$hydrated): void {
            $hydrated++;
        });

        $first = $this->get(route('literatures.show', $literature));
        $firstHydrated = $hydrated;
        $second = $this->get(route('literatures.show', [$literature, 'reviews_page' => 2]));

        $this->assertCount(12, $first->inertiaProps('reviews'));
        $this->assertLessThanOrEqual(12, $firstHydrated);
        $this->assertSame(120, $first->inertiaProps('ratingSummary.count'));
        $this->assertEquals(4.0, $first->inertiaProps('ratingSummary.average'));
        $this->assertSame($first->inertiaProps('ratingSummary'), $second->inertiaProps('ratingSummary'));
        $this->assertSame(120, $first->inertiaProps('reviewPagination.total'));
        $this->assertSame(10, $first->inertiaProps('reviewPagination.last_page'));
        $this->assertSame(2, $second->inertiaProps('reviewPagination.current_page'));
        $this->assertSame($reviews->take(12)->pluck('id')->all(), collect($first->inertiaProps('reviews'))->pluck('id')->all());
        $this->assertSame($reviews->slice(12, 12)->values()->pluck('id')->all(), collect($second->inertiaProps('reviews'))->pluck('id')->all());
        $this->assertStringContainsString('reviews_page=2', $first->inertiaProps('reviewPagination.next_page_url'));
        $this->assertStringEndsWith('#reviews', $first->inertiaProps('reviewPagination.next_page_url'));
    }

    public function test_latest_visible_review_per_reader_is_selected_before_filtering_written_reviews(): void
    {
        $this->freezeTime();
        $preferred = Literature::factory()->create();
        $sibling = Literature::factory()->create();
        $this->canonicalize($preferred, $sibling);
        $ratingOnlyReader = User::factory()->create();
        Review::factory()->for($preferred)->for($ratingOnlyReader)->create([
            'rating' => 5, 'body' => 'Obsolete written review', 'updated_at' => now()->subDay(),
        ]);
        Review::factory()->for($sibling)->for($ratingOnlyReader)->create(['rating' => 2, 'body' => null]);
        $visibleReader = User::factory()->create();
        $visible = Review::factory()->for($preferred)->for($visibleReader)->create([
            'rating' => 5, 'updated_at' => now()->subDay(),
        ]);
        Review::factory()->for($sibling)->for($visibleReader)->create(['rating' => 1, 'hidden_at' => now()]);
        $other = Review::factory()->for($sibling)->create(['rating' => 4, 'created_at' => now()->subMinute()]);

        $response = $this->get(route('literatures.show', $preferred));

        $this->assertSame(3, $response->inertiaProps('ratingSummary.count'));
        $this->assertEqualsWithDelta(11 / 3, $response->inertiaProps('ratingSummary.average'), 0.00001);
        $this->assertSame([$visible->id, $other->id], collect($response->inertiaProps('reviews'))->pluck('id')->all());
        $this->assertSame(2, $response->inertiaProps('reviewPagination.total'));
    }

    public function test_viewer_review_and_like_state_remain_correct_when_their_review_is_off_the_first_page(): void
    {
        $this->freezeTime();
        $viewer = User::factory()->create();
        $literature = Literature::factory()->create();
        $ownReview = Review::factory()->for($viewer)->for($literature)->create([
            'rating' => 3.5, 'body' => 'My earlier review', 'created_at' => now()->subYear(),
        ]);
        $recent = Review::factory()->count(13)->for($literature)->create();
        $recent->last()->likes()->create(['user_id' => $viewer->id]);

        $response = $this->actingAs($viewer)->get(route('literatures.show', $literature));

        $this->assertSame($ownReview->id, $response->inertiaProps('viewer.current_review.id'));
        $this->assertSame(3.5, $response->inertiaProps('viewer.current_review.rating'));
        $this->assertSame('My earlier review', $response->inertiaProps('viewer.current_review.body'));
        $this->assertNotContains($ownReview->id, collect($response->inertiaProps('reviews'))->pluck('id')->all());
        $liked = collect($response->inertiaProps('reviews'))->firstWhere('id', $recent->last()->id);
        $this->assertTrue($liked['is_liked']);
        $this->assertSame(1, $liked['likes_count']);
    }

    public function test_detail_related_cards_eager_load_canonical_metadata_instead_of_lazy_loading_per_card(): void
    {
        $literature = Literature::factory()->create();
        $author = Author::factory()->create();
        $literature->authors()->attach($author);
        Literature::factory()->count(20)->create()->each(function (Literature $related) use ($literature, $author): void {
            $related->authors()->attach($author);
            $sibling = Literature::factory()->create();
            $work = $this->canonicalize($related, $sibling);
            LiteratureMetadataOverride::factory()->for($related)->for($work)->create();
            LiteratureRelation::factory()->for($literature)->for($related, 'relatedLiterature')->create();
        });
        Model::preventLazyLoading();

        try {
            $response = $this->get(route('literatures.show', $literature));
            $response->assertOk();
            $this->assertCount(20, collect($response->inertiaProps('relationshipGroups'))->flatMap(fn ($group) => $group['items']));
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_browse_total_count_does_not_repeat_popularity_aggregates(): void
    {
        Literature::factory()->count(3)->create();
        $browser = app(LiteratureBrowseService::class);
        DB::enableQueryLog();

        try {
            $page = $browser->browse($browser->normalizeFilters([]));
            $countQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_starts_with(strtolower($query['query']), 'select count('));

            $this->assertSame(3, $page->total());
            $this->assertCount(1, $countQueries);
            $this->assertStringNotContainsString('activities', $countQueries->first()['query']);
            $this->assertStringNotContainsString('reading_lists', $countQueries->first()['query']);
            $this->assertStringNotContainsString('reviews', $countQueries->first()['query']);
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_rolling_window_expires_activity_and_favorites_without_waiting_for_a_cache_ttl(): void
    {
        $this->freezeTime();
        $literature = Literature::factory()->create();
        $user = User::factory()->create();
        Activity::factory()->for($user)->for($literature)->create([
            'type' => Activity::TYPE_STARTED_READING, 'occurred_at' => now()->subDays(7),
        ]);
        $user->favoriteLiteratures()->attach($literature, ['position' => 0, 'created_at' => now()->subDays(7)]);
        $browser = app(LiteratureBrowseService::class);
        $before = $browser->popular()->first();

        $this->travel(1)->seconds();
        $after = $browser->popular()->first();

        $this->assertEquals(4, $before->weekly_popularity_score);
        $this->assertEquals(0, $after->weekly_popularity_score);
        $this->assertEquals(3, $after->overall_popularity_score);
    }

    public function test_popularity_keeps_the_existing_community_weights_and_reflects_new_activity_immediately(): void
    {
        $this->freezeTime();
        $literature = Literature::factory()->create();
        $user = User::factory()->create();
        ReadingList::factory()->for($user)->for($literature)->create(['status' => 'completed']);
        $review = Review::factory()->for($user)->for($literature)->create(['rating' => 4]);
        $user->favoriteLiteratures()->attach($literature, ['position' => 0]);
        foreach ([Activity::TYPE_STARTED_READING, Activity::TYPE_COMPLETED, Activity::TYPE_REVIEWED] as $type) {
            Activity::factory()->for($user)->for($literature)->create(['type' => $type, 'review_id' => $review->id]);
        }
        $browser = app(LiteratureBrowseService::class);
        $before = $browser->popular()->first();

        Activity::factory()->for($literature)->create(['type' => Activity::TYPE_STARTED_READING]);
        $after = $browser->popular()->first();

        $this->assertEquals(10, $before->weekly_popularity_score);
        $this->assertEquals(11, $after->weekly_popularity_score);
        $this->assertEquals(7, $after->overall_popularity_score);
    }

    private function canonicalize(Literature $preferred, Literature $sibling): CanonicalWork
    {
        $work = CanonicalWork::factory()->create([
            'preferred_literature_id' => $preferred->id, 'canonical_title' => $preferred->title, 'type' => $preferred->type,
        ]);
        foreach ([$preferred, $sibling] as $literature) {
            LiteratureSourceMapping::factory()->for($work)->for($literature)->create();
        }

        return $work;
    }
}
