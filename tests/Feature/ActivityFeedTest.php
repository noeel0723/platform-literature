<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Discussion;
use App\Models\Literature;
use App\Models\ReadingList;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActivityFeedTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_home_contains_only_the_activity_feed_and_not_the_catalog_sections(): void
    {
        Literature::factory()->create(['title' => 'Catalog Only Literature']);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home/Index')
                ->where('viewer', null)
                ->has('activities', 0)
                ->has('popularLiteratures', 0));
    }

    public function test_guest_is_invited_to_login_instead_of_receiving_a_global_activity_feed(): void
    {
        $reader = User::factory()->create(['name' => 'Community Reader']);
        $literature = Literature::factory()->create(['title' => 'Shared Reading']);
        Activity::factory()->for($reader)->for($literature)->create([
            'type' => Activity::TYPE_COMPLETED,
        ]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home/Index')
                ->where('viewer', null)
                ->has('activities', 0)
                ->has('popularLiteratures', 0)
                ->where('routes.login', route('login')));
    }

    public function test_authenticated_feed_contains_followed_activity_only(): void
    {
        $viewer = User::factory()->create(['name' => 'Feed Viewer']);
        $followed = User::factory()->create(['name' => 'Followed Reader']);
        $stranger = User::factory()->create(['name' => 'Unfollowed Reader']);
        $viewer->following()->attach($followed);

        Activity::factory()->for($viewer)->for(Literature::factory()->create(['title' => 'My Own Book']))->create(['type' => Activity::TYPE_COMPLETED]);
        Activity::factory()->for($followed)->for(Literature::factory()->create(['title' => 'Followed Book']))->create(['type' => Activity::TYPE_COMPLETED]);
        Activity::factory()->for($stranger)->for(Literature::factory()->create(['title' => 'Hidden Stranger Book']))->create(['type' => Activity::TYPE_COMPLETED]);

        $response = $this->actingAs($viewer)->get(route('home'))->assertOk();

        $this->assertSame(['Followed Book'], collect($response->inertiaProps('activities'))->pluck('literature.title')->all());
    }

    public function test_friends_activity_is_limited_to_the_six_latest_distinct_works(): void
    {
        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $viewer->following()->attach($friend);

        foreach (range(1, 7) as $position) {
            Activity::factory()
                ->for($friend)
                ->for(Literature::factory()->create(['title' => "Friend Activity {$position}"]))
                ->create([
                    'type' => Activity::TYPE_COMPLETED,
                    'occurred_at' => Carbon::parse("2026-09-0{$position} 12:00:00", 'UTC'),
                ]);
        }

        $response = $this->actingAs($viewer)->get(route('home'))->assertOk();

        $this->assertCount(6, $response->inertiaProps('activities'));
        $this->assertSame('Friend Activity 7', $response->inertiaProps('activities.0.literature.title'));
        $this->assertNotContains('Friend Activity 1', collect($response->inertiaProps('activities'))->pluck('literature.title')->all());
    }

    public function test_popular_with_friends_is_ranked_by_the_number_of_friends_reading_each_work(): void
    {
        $viewer = User::factory()->create();
        $firstFriend = User::factory()->create();
        $secondFriend = User::factory()->create();
        $viewer->following()->attach([$firstFriend->id, $secondFriend->id]);
        $popular = Literature::factory()->create(['title' => 'Two Friends Favorite']);
        $single = Literature::factory()->create(['title' => 'One Friend Favorite']);

        ReadingList::factory()->for($firstFriend)->for($popular)->create(['status' => 'completed']);
        ReadingList::factory()->for($secondFriend)->for($popular)->create(['status' => 'reading']);
        ReadingList::factory()->for($firstFriend)->for($single)->create(['status' => 'completed']);

        $response = $this->actingAs($viewer)->get(route('home'))->assertOk();

        $this->assertSame(
            ['Two Friends Favorite', 'One Friend Favorite'],
            collect($response->inertiaProps('popularLiteratures'))->pluck('title')->all(),
        );
        $this->assertSame([2, 1], collect($response->inertiaProps('popularLiteratures'))->pluck('friends_count')->all());
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
        $viewer = User::factory()->create();
        $user = User::factory()->create(['name' => 'Reviewing Reader']);
        $viewer->following()->attach($user);
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

        $response = $this->actingAs($viewer)->get(route('home'))->assertOk();

        $this->assertSame('Reviewed', $response->inertiaProps('activities.0.action'));
        $this->assertSame('Reviewed Story', $response->inertiaProps('activities.0.literature.title'));
        $this->assertSame(4.5, $response->inertiaProps('activities.0.rating'));
        $this->assertSame('Reviewing Reader', $response->inertiaProps('activities.0.reader.name'));
    }

    public function test_feed_exposes_utc_activity_for_browser_localization(): void
    {
        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $viewer->following()->attach($friend);
        Activity::factory()->for($friend)->create([
            'type' => Activity::TYPE_COMPLETED,
            'occurred_at' => Carbon::parse('2026-09-07 02:30:00', 'UTC'),
        ]);

        $this->actingAs($viewer)->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home/Index')
                ->where('activities.0.occurred_at', '2026-09-07T02:30:00+00:00'));
    }

    public function test_hidden_reviews_are_not_exposed_in_the_activity_feed(): void
    {
        $review = Review::factory()->create([
            'body' => 'Moderated review body.',
            'hidden_at' => now(),
        ]);
        $viewer = User::factory()->create();
        $viewer->following()->attach($review->user);
        Activity::factory()->for($review->user)->for($review->literature)->create([
            'review_id' => $review->id,
            'type' => Activity::TYPE_REVIEWED,
            'metadata' => [
                'rating' => 4,
                'review_excerpt' => 'Moderated review body.',
                'contains_spoiler' => false,
            ],
        ]);

        $this->actingAs($viewer)->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home/Index')
                ->has('activities', 0));
    }

    public function test_activity_page_requires_login_and_combines_the_reader_with_followed_friends(): void
    {
        $viewer = User::factory()->create(['name' => 'Activity Owner']);
        $friend = User::factory()->create(['name' => 'Followed Activity Reader']);
        $stranger = User::factory()->create(['name' => 'Unrelated Activity Reader']);
        $viewer->following()->attach($friend);

        Activity::factory()->for($viewer)->for(Literature::factory()->create(['title' => 'Owner Activity']))->create(['type' => Activity::TYPE_COMPLETED]);
        Activity::factory()->for($friend)->for(Literature::factory()->create(['title' => 'Friend Activity']))->create(['type' => Activity::TYPE_COMPLETED]);
        Activity::factory()->for($stranger)->for(Literature::factory()->create(['title' => 'Stranger Activity']))->create(['type' => Activity::TYPE_COMPLETED]);

        $this->get(route('activity.index'))->assertRedirect(route('login'));

        $response = $this->actingAs($viewer)->get(route('activity.index'))->assertOk();

        $this->assertSame('Activity/Index', $response->inertiaPage()['component']);
        $this->assertSame(['Friend Activity', 'Owner Activity'], collect($response->inertiaProps('activities.data'))->pluck('literature.title')->sort()->values()->all());
        $this->assertSame(['You + friends', 'Your activity', 'Following'], collect($response->inertiaProps('scopes'))->pluck('label')->all());
        $this->assertSame('all', $response->inertiaProps('scope'));

        $friendsResponse = $this->actingAs($viewer)->get(route('activity.index', ['scope' => 'friends']))->assertOk();

        $this->assertSame(['Friend Activity'], collect($friendsResponse->inertiaProps('activities.data'))->pluck('literature.title')->all());
        $this->assertSame('friends', $friendsResponse->inertiaProps('scope'));
    }

    public function test_mutual_friend_can_view_a_readers_activity_and_their_following_stream(): void
    {
        $viewer = User::factory()->create(['name' => 'Visiting Friend']);
        $friend = User::factory()->create(['name' => 'Profile Friend']);
        $followedReader = User::factory()->create(['name' => 'Friend Following']);
        $stranger = User::factory()->create(['name' => 'Unrelated Reader']);
        $viewer->following()->attach($friend);
        $friend->following()->attach([$viewer->id, $followedReader->id]);

        Activity::factory()->for($friend)->for(Literature::factory()->create(['title' => 'Friend Own Activity']))->create(['type' => Activity::TYPE_COMPLETED]);
        Activity::factory()->for($followedReader)->for(Literature::factory()->create(['title' => 'Following Activity']))->create(['type' => Activity::TYPE_COMPLETED]);
        Activity::factory()->for($stranger)->for(Literature::factory()->create(['title' => 'Hidden Stranger Activity']))->create(['type' => Activity::TYPE_COMPLETED]);

        $this->get(route('profiles.activity', $friend))->assertRedirect(route('login'));

        $friendResponse = $this->actingAs($viewer)->get(route('profiles.activity', $friend))->assertOk();
        $this->assertSame(['Teman', 'Following'], collect($friendResponse->inertiaProps('scopes'))->pluck('short_label')->all());
        $this->assertSame(['Friend Own Activity'], collect($friendResponse->inertiaProps('activities.data'))->pluck('literature.title')->all());

        $followingResponse = $this->actingAs($viewer)->get(route('profiles.activity', [$friend, 'scope' => 'following']))->assertOk();
        $this->assertEqualsCanonicalizing(
            ['Following Activity'],
            collect($followingResponse->inertiaProps('activities.data'))->pluck('literature.title')->all(),
        );
        $this->assertSame('following', $followingResponse->inertiaProps('scope'));
    }

    public function test_non_friend_cannot_view_another_readers_activity(): void
    {
        $viewer = User::factory()->create();
        $reader = User::factory()->create();

        $this->actingAs($viewer)->get(route('profiles.activity', $reader))->assertForbidden();
    }

    public function test_readlist_discussion_and_comment_actions_are_recorded_for_the_activity_page(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create(['title' => 'Shared Activity Story']);

        $this->actingAs($user)->put(route('reading-list.update', $literature), [
            'status' => 'want_to_read',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('discussions.store', $literature), [
            'title' => 'A closer look at the ending',
            'discussion_body' => 'The final chapter changes how the opening should be understood.',
        ])->assertRedirect();

        $discussion = Discussion::query()->sole();

        $this->actingAs($user)->post(route('discussions.comments.store', $discussion), [
            'comment_body' => 'The recurring image supports this interpretation very clearly.',
        ])->assertRedirect();

        $comment = Comment::query()->sole();

        $this->assertDatabaseHas('activities', [
            'user_id' => $user->id,
            'literature_id' => $literature->id,
            'type' => Activity::TYPE_ADDED_TO_READLIST,
        ]);
        $this->assertDatabaseHas('activities', [
            'discussion_id' => $discussion->id,
            'type' => Activity::TYPE_DISCUSSION,
        ]);
        $this->assertDatabaseHas('activities', [
            'comment_id' => $comment->id,
            'type' => Activity::TYPE_COMMENT,
        ]);

        $response = $this->actingAs($user)->get(route('activity.index', ['scope' => 'you']))->assertOk();

        $this->assertEqualsCanonicalizing(
            ['added to Readlist', 'started a discussion about', 'commented on a discussion about'],
            collect($response->inertiaProps('activities.data'))->pluck('action')->all(),
        );
        $this->assertContains('A closer look at the ending', collect($response->inertiaProps('activities.data'))->pluck('discussion_title')->filter()->all());
    }

    public function test_hidden_discussions_and_comments_are_excluded_from_activity(): void
    {
        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $viewer->following()->attach($friend);
        $literature = Literature::factory()->create(['title' => 'Moderated Activity Story']);
        $discussion = Discussion::factory()->for($friend)->for($literature)->create([
            'hidden_at' => now(),
        ]);
        $comment = Comment::factory()->for($friend)->for($discussion)->create();

        Activity::factory()->for($friend)->for($literature)->create([
            'discussion_id' => $discussion->id,
            'type' => Activity::TYPE_DISCUSSION,
        ]);
        Activity::factory()->for($friend)->for($literature)->create([
            'discussion_id' => $discussion->id,
            'comment_id' => $comment->id,
            'type' => Activity::TYPE_COMMENT,
        ]);

        $response = $this->actingAs($viewer)->get(route('activity.index'))->assertOk();

        $this->assertSame([], $response->inertiaProps('activities.data'));
    }
}
