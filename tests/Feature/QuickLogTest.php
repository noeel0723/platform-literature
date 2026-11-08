<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\CanonicalWork;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Models\ReadingList;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuickLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_must_login_to_search_the_quick_log_catalog(): void
    {
        $this->getJson(route('quick-log.literatures', ['q' => 'Haikyuu']))
            ->assertUnauthorized();
    }

    public function test_authenticated_header_provides_the_react_quick_log_search_route(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertSee('data-react-header-props', false)
            ->assertSee('quick_log_search', false)
            ->assertDontSee('id="quick-log-search-dialog"', false);
    }

    public function test_guest_header_does_not_contain_the_quick_log_workflow(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('quick-log.literatures'), false)
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
        ReadingList::factory()->for($user)->for($matchingLiterature)->create([
            'status' => 'completed',
            'completed_at' => '2026-09-03 00:00:00',
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
            ->assertJsonPath('data.0.completed_at', '2026-09-03')
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

    public function test_quick_log_prioritizes_the_popular_exact_title_before_newer_imports_and_metadata_quality(): void
    {
        Http::preventStrayRequests();
        $viewer = User::factory()->create();
        $popular = Literature::factory()->create([
            'title' => 'Naruto', 'type' => 'manga', 'synopsis' => null, 'publisher' => null,
            'identifier' => null, 'publication_year' => null, 'updated_at' => '2020-01-01',
        ]);
        User::factory()->count(3)->create()->each(fn (User $reader) => ReadingList::factory()->for($reader)->for($popular)->create(['status' => 'completed']));
        for ($index = 0; $index < 12; $index++) {
            $edition = Literature::factory()->create(['title' => 'Naruto', 'type' => 'western-comic', 'updated_at' => now()]);
            $edition->authors()->attach(Author::factory()->create());
        }
        $this->actingAs($viewer)->getJson(route('quick-log.literatures', ['q' => 'naruto']))
            ->assertOk()->assertJsonCount(8, 'data')->assertJsonPath('data.0.id', $popular->id)->assertJsonPath('data.0.type', 'Manga');
        Http::assertNothingSent();
    }

    public function test_popular_partial_title_cannot_outrank_an_exact_title(): void
    {
        $viewer = User::factory()->create();
        $exact = Literature::factory()->create(['title' => 'Naruto', 'type' => 'manga']);
        $sequel = Literature::factory()->create(['title' => 'Naruto Adventures', 'type' => 'manga']);
        User::factory()->count(4)->create()->each(fn (User $reader) => ReadingList::factory()->for($reader)->for($sequel)->create(['status' => 'completed']));
        $response = $this->actingAs($viewer)->getJson(route('quick-log.literatures', ['q' => 'Naruto']))->assertOk();
        $this->assertSame([$exact->id, $sequel->id], array_column($response->json('data'), 'id'));
    }

    public function test_equal_partial_matches_are_sorted_by_popularity_instead_of_recency(): void
    {
        $viewer = User::factory()->create();
        $popular = Literature::factory()->create(['title' => 'Naruto Chronicles', 'type' => 'manga', 'updated_at' => '2020-01-01']);
        $newer = Literature::factory()->create(['title' => 'Naruto Adventures', 'type' => 'manga', 'updated_at' => now()]);
        ReadingList::factory()->for($popular)->create(['status' => 'reading']);
        $response = $this->actingAs($viewer)->getJson(route('quick-log.literatures', ['q' => 'Naru']))->assertOk();
        $this->assertSame([$popular->id, $newer->id], array_column($response->json('data'), 'id'));
    }

    public function test_canonical_popularity_counts_unique_readers_across_editions_and_keeps_the_viewers_review(): void
    {
        $viewer = User::factory()->create();
        $preferred = Literature::factory()->create(['title' => 'Naruto', 'type' => 'manga']);
        $sibling = Literature::factory()->create(['title' => 'Naruto', 'type' => 'manga']);
        $work = CanonicalWork::factory()->create(['preferred_literature_id' => $preferred->id]);
        foreach ([$preferred, $sibling] as $source) {
            LiteratureSourceMapping::factory()->create(['literature_id' => $source->id, 'api_source_id' => $source->api_source_id, 'canonical_work_id' => $work->id]);
        }
        foreach ([$preferred, $sibling] as $source) {
            ReadingList::factory()->for($viewer)->for($source)->create(['status' => 'completed']);
        }
        Review::factory()->for($viewer)->for($sibling, 'literature')->create(['rating' => 4.5, 'body' => 'My saved review']);
        $viewer->favoriteLiteratures()->attach($sibling, ['position' => 0]);
        $other = Literature::factory()->create(['title' => 'Naruto', 'type' => 'western-comic']);
        User::factory()->count(2)->create()->each(fn (User $reader) => ReadingList::factory()->for($reader)->for($other)->create(['status' => 'completed']));
        $response = $this->actingAs($viewer)->getJson(route('quick-log.literatures', ['q' => 'Naruto']))->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame([$other->id, $preferred->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('data.1.review.body', 'My saved review')->assertJsonPath('data.1.review.rating', 4.5)
            ->assertJsonPath('data.1.review_url', route('reviews.update', $preferred));
    }

    public function test_inactive_readers_and_hidden_reviews_do_not_boost_quick_log_results(): void
    {
        $viewer = User::factory()->create();
        $active = Literature::factory()->create(['title' => 'Naruto', 'type' => 'manga']);
        $inactive = Literature::factory()->create(['title' => 'Naruto', 'type' => 'western-comic']);
        ReadingList::factory()->for($active)->create(['status' => 'completed']);
        $deactivated = User::factory()->create(['deactivated_at' => now()]);
        ReadingList::factory()->for($deactivated)->for($inactive, 'literature')->create(['status' => 'completed']);
        Review::factory()->for($inactive, 'literature')->create(['hidden_at' => now()]);
        $this->actingAs($viewer)->getJson(route('quick-log.literatures', ['q' => 'Naruto']))->assertJsonPath('data.0.id', $active->id);
    }
}
