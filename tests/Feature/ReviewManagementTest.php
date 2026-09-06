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

    public function test_detail_page_shows_review_summary_and_spoiler_control(): void
    {
        $literature = Literature::factory()->create(['title' => 'A Reviewable Story']);
        $review = Review::factory()->for($literature)->for(User::factory()->create(['name' => 'Reader One']))->create([
            'rating' => 4,
            'body' => 'The final chapter changes everything.',
            'contains_spoiler' => true,
        ]);

        $this->get(route('literatures.show', $literature))
            ->assertOk()
            ->assertSeeText('4.0')
            ->assertSeeText('Reader One')
            ->assertSeeText('Reveal spoiler review')
            ->assertSee('id="review-body-'.$review->id.'" hidden', false)
            ->assertSeeText('The final chapter changes everything.');
    }
}
