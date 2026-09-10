<?php

namespace Tests\Feature;

use App\Models\Literature;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

        $this->get(route('profiles.reviews', $reader))
            ->assertOk()
            ->assertSeeText("Public Reviewer's Reviews")
            ->assertSeeText('Visible Review Title')
            ->assertSeeText('A thoughtful visible review.')
            ->assertSeeText('4.5')
            ->assertDontSeeText('Hidden Review Title')
            ->assertDontSeeText('Other Reader Title')
            ->assertSee('data-profile-review', false)
            ->assertSee('data-density="compact"', false)
            ->assertSee('data-review-summary', false);
    }

    public function test_owner_reviews_page_has_aligned_navigation_and_edit_link(): void
    {
        $reader = User::factory()->create();
        $literature = Literature::factory()->create();
        Review::factory()->for($reader)->for($literature)->create();

        $this->actingAs($reader)
            ->get(route('profiles.reviews', $reader))
            ->assertOk()
            ->assertSee('data-react-profile-subnav', false)
            ->assertSee('&quot;current&quot;:&quot;reviews&quot;', false)
            ->assertSeeInOrder(['Profile', 'Activity', 'Literature', 'Diary', 'Reviews', 'Readlist'])
            ->assertSee(route('literatures.show', $literature).'?review=edit', false);
    }

    public function test_spoiler_review_body_is_hidden_until_revealed(): void
    {
        $reader = User::factory()->create();
        Review::factory()->spoiler()->for($reader)->create(['body' => 'The ending is revealed here.']);

        $this->get(route('profiles.reviews', $reader))
            ->assertOk()
            ->assertSeeText('Reveal spoiler review')
            ->assertSee('hidden class=', false)
            ->assertSeeText('The ending is revealed here.');
    }
}
