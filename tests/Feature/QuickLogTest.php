<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Literature;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class QuickLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_must_login_to_search_the_quick_log_catalog(): void
    {
        $this->getJson(route('quick-log.literatures', ['q' => 'Haikyuu']))
            ->assertUnauthorized();
    }

    public function test_authenticated_header_contains_the_quick_log_workflow(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertSee('data-react-header-props', false)
            ->assertSee('id="quick-log-search-dialog"', false)
            ->assertSee('id="quick-log-review-dialog"', false)
            ->assertSee('data-search-url="'.route('quick-log.literatures').'"', false)
            ->assertSee('data-quick-log-review-form', false);
    }

    public function test_guest_header_does_not_contain_the_quick_log_workflow(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-quick-log-open', false)
            ->assertDontSee('id="quick-log-search-dialog"', false);
    }

    public function test_quick_log_search_returns_matching_literature_and_the_users_review(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $author = Author::factory()->create(['name' => 'Haruichi Furudate']);
        $matchingLiterature = Literature::factory()->create([
            'title' => 'Haikyuu!!',
            'publication_year' => 2012,
            'type' => 'manga',
        ]);
        $matchingLiterature->authors()->attach($author, ['role' => 'author', 'position' => 1]);
        $ownReview = Review::factory()->for($user)->for($matchingLiterature)->create([
            'rating' => 4.5,
            'body' => 'A precise and energetic sports story.',
            'contains_spoiler' => true,
        ]);
        Review::factory()->for($otherUser)->for($matchingLiterature)->create([
            'rating' => 1,
            'body' => 'This must never be returned.',
        ]);
        Literature::factory()->create(['title' => 'Unrelated Story']);

        $this->actingAs($user)
            ->getJson(route('quick-log.literatures', ['q' => 'haikyuu']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Haikyuu!!')
            ->assertJsonPath('data.0.year', 2012)
            ->assertJsonPath('data.0.type', 'Manga')
            ->assertJsonPath('data.0.authors.0', 'Haruichi Furudate')
            ->assertJsonPath('data.0.review_url', route('reviews.update', $matchingLiterature))
            ->assertJsonPath('data.0.review.rating', 4.5)
            ->assertJsonPath('data.0.review.body', $ownReview->body)
            ->assertJsonPath('data.0.review.contains_spoiler', true)
            ->assertJsonMissing(['body' => 'This must never be returned.']);
    }

    public function test_quick_log_search_validates_query_length(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('quick-log.literatures', ['q' => str_repeat('a', 101)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }
}
