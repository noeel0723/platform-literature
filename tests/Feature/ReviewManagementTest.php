<?php

namespace Tests\Feature;

use App\Models\Literature;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReviewManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_must_login_to_publish_a_review(): void
    {
        $literature = Literature::factory()->create();

        $this->put(route('reviews.update', $literature), [
            'rating' => 5,
            'body' => 'A thoughtful review.',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_guest_must_login_to_delete_a_review(): void
    {
        $literature = Literature::factory()->create();

        $this->delete(route('reviews.destroy', $literature))
            ->assertRedirect(route('login'));
    }

    public function test_user_can_rate_and_review_a_literature(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();

        $this->actingAs($user)->put(route('reviews.update', $literature), [
            'rating' => 5,
            'body' => 'A powerful ending.',
            'contains_spoiler' => true,
        ])->assertRedirect(route('literatures.show', $literature).'#reviews');

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'literature_id' => $literature->id,
            'rating' => 5,
            'body' => 'A powerful ending.',
            'contains_spoiler' => true,
        ]);
        $this->assertDatabaseHas('reading_lists', [
            'user_id' => $user->id,
            'literature_id' => $literature->id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('reading_logs', ['event_type' => 'completed']);
    }

    public function test_user_can_save_a_half_star_rating(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();

        $this->actingAs($user)->put(route('reviews.update', $literature), [
            'rating' => 2.5,
            'body' => 'Promising, although the middle section is uneven.',
        ])->assertRedirect(route('literatures.show', $literature).'#reviews');

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'literature_id' => $literature->id,
            'rating' => 2.5,
        ]);
    }

    public function test_updating_a_review_reuses_the_same_record(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();
        Review::factory()->for($user)->for($literature)->create(['rating' => 3]);

        $this->actingAs($user)->put(route('reviews.update', $literature), [
            'rating' => 4,
            'body' => 'Better after a reread.',
        ]);

        $this->assertDatabaseCount('reviews', 1);
        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'literature_id' => $literature->id,
            'rating' => 4,
            'body' => 'Better after a reread.',
            'contains_spoiler' => false,
        ]);
    }

    public function test_user_can_delete_their_review_from_the_edit_dialog(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();
        Review::factory()->for($user)->for($literature)->create();

        $this->actingAs($user)
            ->delete(route('reviews.destroy', $literature))
            ->assertRedirect(route('literatures.show', $literature).'#reviews')
            ->assertSessionHas('success', 'Your review has been deleted.');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_user_cannot_delete_another_users_review(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();
        $review = Review::factory()->for(User::factory())->for($literature)->create();

        $this->actingAs($user)->delete(route('reviews.destroy', $literature));

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    public function test_rating_must_be_between_one_and_five(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();

        $this->actingAs($user)
            ->from(route('literatures.show', $literature).'#reviews')
            ->put(route('reviews.update', $literature), ['rating' => 6])
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_rating_must_use_half_star_steps(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();

        $this->actingAs($user)
            ->from(route('literatures.show', $literature).'#reviews')
            ->put(route('reviews.update', $literature), ['rating' => 2.3])
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_detail_page_shows_review_summary_and_spoiler_control(): void
    {
        $literature = Literature::factory()->create(['title' => 'A Reviewable Story']);
        $review = Review::factory()->for($literature)->for(User::factory()->create(['name' => 'Reader One']))->create([
            'rating' => 4,
            'body' => 'The final chapter changes everything.',
            'contains_spoiler' => true,
        ]);

        $response = $this->get(route('literatures.show', $literature));

        $response->assertOk();
        $this->assertEquals(4.0, $response->inertiaProps('ratingSummary.average'));
        $this->assertSame($review->id, $response->inertiaProps('reviews.0.id'));
        $this->assertSame('Reader One', $response->inertiaProps('reviews.0.user.name'));
        $this->assertTrue($response->inertiaProps('reviews.0.contains_spoiler'));
        $this->assertSame('The final chapter changes everything.', $response->inertiaProps('reviews.0.body'));
    }

    public function test_authenticated_detail_page_uses_review_dialog_and_half_star_controls(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();

        $response = $this->actingAs($user)->get(route('literatures.show', $literature));

        $response->assertOk();
        $this->assertTrue($response->inertiaProps('viewer.authenticated'));
        $this->assertNull($response->inertiaProps('viewer.current_review'));
        $this->assertSame(route('reviews.update', $literature), $response->inertiaProps('routes.review_update'));
        $this->assertSame(route('reading-list.update', $literature), $response->inertiaProps('routes.reading_update'));
        $this->assertSame(route('reading-list.destroy', $literature), $response->inertiaProps('routes.reading_destroy'));
    }

    public function test_edit_review_dialog_offers_review_deletion(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();
        Review::factory()->for($user)->for($literature)->create();

        $response = $this->actingAs($user)->get(route('literatures.show', $literature));

        $response->assertOk();
        $this->assertNotNull($response->inertiaProps('viewer.current_review'));
        $this->assertSame(route('reviews.destroy', $literature), $response->inertiaProps('routes.review_destroy'));
    }
}
