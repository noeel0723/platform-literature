<?php

namespace Tests\Feature;

use App\Models\Literature;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileReviewPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_reviews_page_only_shows_visible_reviews_for_selected_reader(): void
    {
        $reader = User::factory()->create(['name' => 'Public Reviewer']);
        $visibleLiterature = Literature::factory()->create(['title' => 'Visible Review Title']);
        $hiddenLiterature = Literature::factory()->create(['title' => 'Hidden Review Title']);
        $otherLiterature = Literature::factory()->create(['title' => 'Other Reader Title']);

        Review::factory()->for($reader)->for($visibleLiterature)->create([
            'rating' => 4.5,
            'body' => 'A thoughtful visible review.',
        ]);
        Review::factory()->for($reader)->for($hiddenLiterature)->create([
            'body' => 'This moderated review must stay hidden.',
            'hidden_at' => now(),
        ]);
        Review::factory()->for(User::factory())->for($otherLiterature)->create();

        $response = $this->get(route('profiles.reviews', $reader))->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Reviews')
            ->where('profile.name', 'Public Reviewer')
            ->where('reviews.total', 1)
            ->where('reviews.data.0.literature.title', 'Visible Review Title')
            ->where('reviews.data.0.body', 'A thoughtful visible review.')
            ->where('reviews.data.0.rating', 4.5)
            ->where('summary.total_reviews', 1)
        );
        $this->assertNotContains('Hidden Review Title', collect($response->inertiaProps('reviews.data'))->pluck('literature.title'));
        $this->assertNotContains('Other Reader Title', collect($response->inertiaProps('reviews.data'))->pluck('literature.title'));
    }

    public function test_owner_reviews_page_has_aligned_navigation_and_edit_link(): void
    {
        $reader = User::factory()->create();
        $literature = Literature::factory()->create();
        Review::factory()->for($reader)->for($literature)->create();

        $response = $this->actingAs($reader)
            ->get(route('profiles.reviews', $reader))
            ->assertOk();

        $this->assertSame('reviews', $response->inertiaProps('navigation.current'));
        $this->assertSame(['Profile', 'Stats', 'Activity', 'Literature', 'Diary', 'Reviews', 'Readlist', 'Lists', 'Connections'], collect($response->inertiaProps('navigation.links'))->pluck('label')->all());
        $this->assertSame(route('literatures.show', $literature).'?review=edit', $response->inertiaProps('reviews.data.0.edit_url'));
    }

    public function test_spoiler_review_body_is_hidden_until_revealed(): void
    {
        $reader = User::factory()->create();
        Review::factory()->spoiler()->for($reader)->create(['body' => 'The ending is revealed here.']);

        $response = $this->get(route('profiles.reviews', $reader))->assertOk();

        $this->assertTrue($response->inertiaProps('reviews.data.0.contains_spoiler'));
        $this->assertSame('The ending is revealed here.', $response->inertiaProps('reviews.data.0.body'));
    }
}
