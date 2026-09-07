<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Literature;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ActivityFeedTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_home_contains_only_the_activity_feed_and_not_the_catalog_sections(): void
    {
        Literature::factory()->create(['title' => 'Catalog Only Literature']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSeeText('Activity Feed')
            ->assertSeeText('Community activity')
            ->assertDontSeeText('Catalog Only Literature')
            ->assertDontSeeText('Metadata sources')
            ->assertDontSeeText('Current scope')
            ->assertDontSee('href="'.route('home').'#sources"', false);
    }

    public function test_guest_can_see_recent_public_community_activity(): void
    {
        $reader = User::factory()->create(['name' => 'Community Reader']);
        $literature = Literature::factory()->create(['title' => 'Shared Reading']);
        Activity::factory()->for($reader)->for($literature)->create([
            'type' => Activity::TYPE_COMPLETED,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSeeText('Community Reader')
            ->assertSeeText('finished reading')
            ->assertSeeText('Shared Reading');
    }

    public function test_authenticated_feed_contains_own_and_followed_activity_only(): void
    {
        $viewer = User::factory()->create(['name' => 'Feed Viewer']);
        $followed = User::factory()->create(['name' => 'Followed Reader']);
        $stranger = User::factory()->create(['name' => 'Unfollowed Reader']);
        $viewer->following()->attach($followed);

        Activity::factory()->for($viewer)->for(Literature::factory()->create(['title' => 'My Own Book']))->create();
        Activity::factory()->for($followed)->for(Literature::factory()->create(['title' => 'Followed Book']))->create();
        Activity::factory()->for($stranger)->for(Literature::factory()->create(['title' => 'Hidden Stranger Book']))->create();

        $this->actingAs($viewer)->get(route('home'))
            ->assertOk()
            ->assertSeeText('You and readers you follow')
            ->assertSeeText('My Own Book')
            ->assertSeeText('Followed Book')
            ->assertDontSeeText('Hidden Stranger Book');
    }

    public function test_reading_updates_create_feed_activities_for_started_completed_and_reread_events(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();

        $this->actingAs($user)->put(route('reading-list.update', $literature), [
            'status' => 'reading',
        ]);
        $this->actingAs($user)->put(route('reading-list.update', $literature), [
            'status' => 'completed',
        ]);
        $this->actingAs($user)->put(route('reading-list.update', $literature), [
            'status' => 'completed',
            'reread' => true,
        ]);

        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'literature_id' => $literature->id,
            'type' => Activity::TYPE_COMPLETED,
        ]);
        $this->assertSame(2, Activity::query()->where('type', Activity::TYPE_STARTED_READING)->count());
        $this->assertDatabaseCount('activities', 3);
    }

    public function test_rating_and_review_create_feed_activity_with_a_safe_excerpt(): void
    {
        $user = User::factory()->create(['name' => 'Reviewing Reader']);
        $literature = Literature::factory()->create(['title' => 'Reviewed Story']);

        $this->actingAs($user)->put(route('reviews.update', $literature), [
            'rating' => 4.5,
            'body' => 'A precise and memorable ending.',
            'contains_spoiler' => true,
        ]);

        $activity = Activity::query()->where('type', Activity::TYPE_REVIEWED)->firstOrFail();

        $this->assertSame(4.5, $activity->metadata['rating']);
        $this->assertSame('A precise and memorable ending.', $activity->metadata['review_excerpt']);
        $this->assertTrue($activity->metadata['contains_spoiler']);

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertSeeText('reviewed')
            ->assertSeeText('Reviewed Story')
            ->assertSeeText('4.5 / 5')
            ->assertSeeText('Review contains spoilers — reveal');
    }

    public function test_feed_exposes_utc_activity_for_browser_localization(): void
    {
        $activity = Activity::factory()->create([
            'occurred_at' => Carbon::parse('2026-09-07 02:30:00', 'UTC'),
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('datetime="2026-09-07T02:30:00+00:00"', false)
            ->assertSee('data-local-datetime', false);
    }

    public function test_hidden_reviews_are_not_exposed_in_the_activity_feed(): void
    {
        $review = Review::factory()->create([
            'body' => 'Moderated review body.',
            'hidden_at' => now(),
        ]);
        Activity::factory()->for($review->user)->for($review->literature)->create([
            'review_id' => $review->id,
            'type' => Activity::TYPE_REVIEWED,
            'metadata' => [
                'rating' => 4,
                'review_excerpt' => 'Moderated review body.',
                'contains_spoiler' => false,
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSeeText('Moderated review body.');
    }
}
