<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\CanonicalWork;
use App\Models\Comment;
use App\Models\CustomList;
use App\Models\Discussion;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ActivityDetailTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_feed_links_open_a_dedicated_activity_with_full_current_review_and_viewer_actions(): void
    {
        $viewer = User::factory()->create();
        $actor = User::factory()->create();
        $viewer->following()->attach($actor);
        $literature = Literature::factory()->create(['title' => 'A Shared Story']);
        $review = Review::factory()->for($actor)->for($literature)->create(['body' => str_repeat('Full review. ', 40), 'rating' => 4.5, 'contains_spoiler' => true]);
        $activity = Activity::factory()->for($actor)->for($literature)->for($review)->create(['type' => Activity::TYPE_REVIEWED, 'metadata' => ['review_excerpt' => 'Stale excerpt', 'rating' => 1]]);
        $url = route('activity.show', $activity);
        $this->actingAs($viewer)->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('activities.0.url', $url));
        $this->get(route('activity.index'))->assertInertia(fn (Assert $page) => $page->where('activities.data.0.url', $url));
        $this->get($url)->assertInertia(fn (Assert $page) => $page->component('Activity/Show')
            ->where('activity.body', $review->body)->where('activity.rating', 4.5)->where('activity.contains_spoiler', true)
            ->where('activity.user.url', route('profiles.show', $actor))->where('activity.is_owner', false)
            ->where('literature.title', 'A Shared Story')->where('literature.url', route('literatures.show', $literature))
            ->where('activity.review.likes_count', 0)->where('activity.review.is_liked', false)
            ->where('preview.literature.review', null)->where('preview.literature.review_url', route('reviews.update', [$literature, 'activity' => $activity->id]))
            ->where('preview.routes.reading_update', route('reading-list.update', $literature))
            ->where('routes.actor_activity', null));
    }

    public function test_completed_log_uses_canonical_rating_and_reading_date_without_inventing_a_review(): void
    {
        $viewer = User::factory()->create();
        $actor = User::factory()->create();
        $viewer->following()->attach($actor);
        $literature = Literature::factory()->create();
        $edition = Literature::factory()->create();
        $work = CanonicalWork::factory()->create(['preferred_literature_id' => $literature->id]);
        foreach ([$literature, $edition] as $source) {
            LiteratureSourceMapping::factory()->create(['literature_id' => $source->id, 'api_source_id' => $source->api_source_id, 'canonical_work_id' => $work->id]);
        }
        Review::factory()->for($actor)->for($edition, 'literature')->create(['rating' => 3.5, 'body' => null]);
        $activity = Activity::factory()->for($actor)->for($literature)->create(['type' => Activity::TYPE_COMPLETED]);
        $actor->readingLists()->update(['completed_at' => '2026-10-01']);
        $this->actingAs($viewer)->get(route('activity.show', $activity))->assertInertia(fn (Assert $page) => $page
            ->where('activity.rating', 3.5)->where('activity.completed_at', '2026-10-01')->where('activity.body', null)->where('activity.review', null));
    }

    public function test_only_readers_who_can_reach_the_existing_feed_can_open_an_activity(): void
    {
        $actor = User::factory()->create();
        $viewer = User::factory()->create();
        $activity = Activity::factory()->for($actor)->create();
        $this->get(route('activity.show', $activity))->assertRedirect(route('login'));
        $this->actingAs($viewer)->get(route('activity.show', $activity))->assertForbidden();
        $this->actingAs($actor)->get(route('activity.show', $activity))->assertInertia(fn (Assert $page) => $page->where('activity.is_owner', true));
        $friend = User::factory()->create();
        $viewer->following()->attach($friend);
        $friend->following()->attach($actor);
        $this->actingAs($viewer)->get(route('activity.show', $activity))->assertForbidden();
        $friend->following()->attach($viewer);
        $this->get(route('activity.show', $activity))->assertOk();
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_blocks_prevent_activity_access_in_both_directions(bool $viewerBlocks): void
    {
        $viewer = User::factory()->create();
        $actor = User::factory()->create();
        $viewer->following()->attach($actor);
        $activity = Activity::factory()->for($actor)->create();
        if ($viewerBlocks) {
            $viewer->blockedUsers()->attach($actor);
        } else {
            $actor->blockedUsers()->attach($viewer);
        }
        $this->actingAs($viewer)->get(route('activity.show', $activity))->assertForbidden();
    }

    public function test_inactive_accounts_hidden_reviews_and_removed_content_do_not_leak_through_direct_urls(): void
    {
        $actor = User::factory()->create();
        $review = Review::factory()->for($actor)->create();
        $activity = Activity::factory()->for($actor)->for($review->literature)->for($review)->create(['type' => Activity::TYPE_REVIEWED]);
        $this->actingAs($actor)->get(route('activity.show', $activity))->assertOk();
        $review->update(['hidden_at' => now()]);
        $this->get(route('activity.show', $activity))->assertNotFound();
        $review->update(['hidden_at' => null, 'body' => null]);
        $this->get(route('activity.show', $activity))->assertNotFound();
        $review->delete();
        $this->get(route('activity.show', $activity))->assertNotFound();
        $other = Activity::factory()->for($actor)->create();
        $actor->update(['deactivated_at' => now()]);
        $this->actingAs(User::factory()->create())->get(route('activity.show', $other))->assertNotFound();
    }

    public function test_unverified_readers_follow_the_existing_optional_verification_setting(): void
    {
        $actor = User::factory()->create();
        $viewer = User::factory()->unverified()->create();
        $viewer->following()->attach($actor);
        $activity = Activity::factory()->for($actor)->create();
        $this->actingAs($viewer)->get(route('activity.show', $activity))->assertRedirect(route('verification.notice'));
        config(['auth.verification.enabled' => false]);
        $this->get(route('activity.show', $activity))->assertOk();
        $this->assertNull($viewer->fresh()->email_verified_at);
    }

    public function test_review_likes_are_idempotent_and_return_json_without_navigating_to_literature(): void
    {
        $viewer = User::factory()->create();
        $review = Review::factory()->create();
        $this->actingAs($viewer)->postJson(route('reviews.likes.store', $review))->assertOk()->assertJsonPath('is_liked', true)->assertJsonPath('likes_count', 1)->assertJsonStructure(['feedback' => ['id', 'message']]);
        $this->postJson(route('reviews.likes.store', $review))->assertJsonPath('likes_count', 1);
        $this->deleteJson(route('reviews.likes.destroy', $review))->assertOk()->assertJsonPath('is_liked', false)->assertJsonPath('likes_count', 0);
        $this->post(route('reviews.likes.store', $review))->assertRedirect(route('literatures.show', $review->literature).'#review-'.$review->id);
    }

    public function test_quick_log_returns_to_activity_and_writes_only_the_viewers_own_review(): void
    {
        $actor = User::factory()->create();
        $viewer = User::factory()->create();
        $viewer->following()->attach($actor);
        $review = Review::factory()->for($actor)->create(['body' => 'Original actor review']);
        $activity = Activity::factory()->for($actor)->for($review->literature)->for($review)->create(['type' => Activity::TYPE_REVIEWED]);
        $this->actingAs($viewer)->put(route('reviews.update', [$review->literature, 'activity' => $activity->id]), ['rating' => 4, 'body' => 'My review', 'contains_spoiler' => false])
            ->assertRedirect(route('activity.show', $activity));
        $this->assertSame('Original actor review', $review->fresh()->body);
        $this->assertSame('My review', $viewer->reviews()->sole()->body);
        $this->get(route('activity.show', $activity))->assertInertia(fn (Assert $page) => $page->where('activity.body', 'Original actor review')->where('preview.literature.review.body', 'My review'));
    }

    public function test_discussion_and_comment_activity_use_live_content_and_respect_moderation(): void
    {
        $actor = User::factory()->create();
        $discussion = Discussion::factory()->for($actor)->create(['body' => 'Full discussion', 'contains_spoiler' => true]);
        $discussionActivity = Activity::factory()->for($actor)->for($discussion->literature)->for($discussion)->create(['type' => Activity::TYPE_DISCUSSION]);
        $comment = Comment::factory()->for($actor)->for($discussion)->create(['body' => 'Full comment']);
        $commentActivity = Activity::factory()->for($actor)->for($discussion->literature)->for($discussion)->for($comment)->create(['type' => Activity::TYPE_COMMENT]);
        $this->actingAs($actor)->get(route('activity.show', $discussionActivity))->assertInertia(fn (Assert $page) => $page->where('activity.body', 'Full discussion')->where('activity.contains_spoiler', true));
        $this->get(route('activity.show', $commentActivity))->assertInertia(fn (Assert $page) => $page->where('activity.body', 'Full comment')->where('activity.contains_spoiler', true));
        $discussion->update(['hidden_at' => now()]);
        $this->get(route('activity.show', $commentActivity))->assertNotFound();
        $this->get(route('activity.show', $discussionActivity))->assertNotFound();
    }

    public function test_mismatched_activity_content_cannot_disclose_another_readers_review(): void
    {
        $actor = User::factory()->create();
        $review = Review::factory()->create();
        $activity = Activity::factory()->for($actor)->for($review->literature)->for($review)->create(['type' => Activity::TYPE_REVIEWED]);
        $this->actingAs($actor)->get(route('activity.show', $activity))->assertNotFound();
    }

    public function test_viewer_reading_and_private_lists_are_independent_from_the_activity_owner(): void
    {
        $actor = User::factory()->create();
        $viewer = User::factory()->create();
        $viewer->following()->attach($actor);
        $activity = Activity::factory()->for($actor)->create();
        CustomList::factory()->for($actor)->create(['is_private' => true]);
        $list = CustomList::factory()->for($viewer)->create(['is_private' => true]);
        $this->actingAs($viewer)->get(route('activity.show', $activity))->assertInertia(fn (Assert $page) => $page->has('preview.lists', 1)->where('preview.lists.0.id', $list->id));
        $this->putJson(route('reading-list.update', $activity->literature), ['status' => 'want_to_read'])->assertOk();
        $this->get(route('activity.show', $activity))->assertInertia(fn (Assert $page) => $page->where('preview.viewer.reading_status', 'want_to_read'));
        $this->assertSame('want_to_read', $viewer->readingLists()->sole()->status);
    }
}
