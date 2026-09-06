<?php

namespace Tests\Feature;

use App\Models\Discussion;
use App\Models\Literature;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LikeManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_must_login_to_like_social_content(): void
    {
        $review = Review::factory()->create();
        $discussion = Discussion::factory()->create();

        $this->post(route('reviews.likes.store', $review))
            ->assertRedirect(route('login'));
        $this->post(route('discussions.likes.store', $discussion))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('likes', 0);
    }

    public function test_user_can_like_a_review_only_once_and_remove_the_like(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();

        $this->actingAs($user)->post(route('reviews.likes.store', $review))
            ->assertRedirect(route('literatures.show', $review->literature).'#review-'.$review->id);
        $this->actingAs($user)->post(route('reviews.likes.store', $review));

        $this->assertDatabaseCount('likes', 1);
        $this->assertDatabaseHas('likes', [
            'user_id' => $user->id,
            'likeable_type' => 'review',
            'likeable_id' => $review->id,
        ]);

        $this->actingAs($user)->delete(route('reviews.likes.destroy', $review))
            ->assertRedirect(route('literatures.show', $review->literature).'#review-'.$review->id);

        $this->assertDatabaseCount('likes', 0);
    }

    public function test_user_can_like_and_unlike_a_discussion(): void
    {
        $user = User::factory()->create();
        $discussion = Discussion::factory()->create();

        $this->actingAs($user)->post(route('discussions.likes.store', $discussion))
            ->assertRedirect(route('literatures.show', $discussion->literature).'#discussion-'.$discussion->id);

        $this->assertDatabaseHas('likes', [
            'user_id' => $user->id,
            'likeable_type' => 'discussion',
            'likeable_id' => $discussion->id,
        ]);

        $this->actingAs($user)->delete(route('discussions.likes.destroy', $discussion))
            ->assertRedirect(route('literatures.show', $discussion->literature).'#discussion-'.$discussion->id);

        $this->assertDatabaseCount('likes', 0);
    }

    public function test_detail_page_shows_like_counts_and_the_current_users_state(): void
    {
        $literature = Literature::factory()->create();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $review = Review::factory()->for($literature)->create();
        $discussion = Discussion::factory()->for($literature)->create();

        $review->likes()->create(['user_id' => $user->id]);
        $review->likes()->create(['user_id' => $otherUser->id]);
        $discussion->likes()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('literatures.show', $literature))
            ->assertOk()
            ->assertSeeText('2 likes')
            ->assertSeeText('1 like')
            ->assertSeeText('Liked')
            ->assertSee('aria-pressed="true"', false)
            ->assertSee(route('reviews.likes.destroy', $review), false)
            ->assertSee(route('discussions.likes.destroy', $discussion), false);
    }

    public function test_deleting_social_content_removes_its_likes(): void
    {
        $review = Review::factory()->create();
        $discussion = Discussion::factory()->create();

        $review->likes()->create(['user_id' => User::factory()->create()->id]);
        $discussion->likes()->create(['user_id' => User::factory()->create()->id]);

        $review->delete();
        $discussion->delete();

        $this->assertDatabaseCount('likes', 0);
    }
}
