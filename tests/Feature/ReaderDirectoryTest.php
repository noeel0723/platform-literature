<?php

namespace Tests\Feature;

use App\Models\CustomList;
use App\Models\ReadingList;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ReaderDirectoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_overview_adds_ten_reader_rows_and_a_view_more_link_without_replacing_top_five_cards(): void
    {
        User::factory()->count(25)->create(['name' => 'Matching Reader']);

        $this->get(route('readers.index', ['q' => 'Matching']))->assertInertia(fn (Assert $page) => $page
            ->component('Readers/Index')->has('readers.data', 5)->has('directory.data', 10)
            ->where('directory.total', 25)->where('directory.more_url', route('readers.all', ['q' => 'Matching']))
            ->missing('directory.data.0.email')->missing('directory.data.0.favorites'));
    }

    public function test_full_directory_remains_accessible_with_fewer_than_ten_readers_and_empty_search_hides_view_more(): void
    {
        User::factory()->count(7)->create();

        $this->get(route('readers.index'))->assertInertia(fn (Assert $page) => $page
            ->has('directory.data', 7)->where('directory.more_url', route('readers.all')));
        $this->get(route('readers.index', ['q' => 'no-matching-reader']))->assertInertia(fn (Assert $page) => $page
            ->has('directory.data', 0)->where('directory.more_url', null));
    }

    public function test_full_directory_paginates_twenty_readers_with_stable_order_and_preserves_search(): void
    {
        Http::preventStrayRequests();
        $readers = User::factory()->count(25)->create(['name' => 'Matching Reader']);
        User::factory()->create(['name' => 'Unrelated Reader', 'username' => 'unrelated']);

        $first = $this->get(route('readers.all', ['q' => 'Matching']))->assertInertia(fn (Assert $page) => $page
            ->component('Readers/All')->has('readers.data', 20)->where('readers.total', 25)
            ->where('readers.per_page', 20)->where('readers.last_page', 2)->where('readers.current_page', 1)
            ->where('readers.data.0.username', $readers[0]->username)->where('readers.data.19.username', $readers[19]->username));
        $second = $this->get($first->inertiaProps('readers.next_page_url'))->assertInertia(fn (Assert $page) => $page
            ->where('query', 'Matching')->has('readers.data', 5)->where('readers.current_page', 2)
            ->where('readers.data.0.username', $readers[20]->username)->where('readers.data.4.username', $readers[24]->username));

        $this->assertSame([], array_intersect(
            array_column($first->inertiaProps('readers.data'), 'username'),
            array_column($second->inertiaProps('readers.data'), 'username'),
        ));
        Http::assertNothingSent();
    }

    public function test_directory_excludes_admins_inactive_accounts_and_both_block_directions(): void
    {
        $viewer = User::factory()->unverified()->create(['name' => 'A Viewer']);
        $visible = User::factory()->create(['name' => 'B Visible']);
        User::factory()->create(['role' => User::ROLE_ADMIN]);
        User::factory()->create(['deactivated_at' => now()]);
        $blocked = User::factory()->create();
        $blocker = User::factory()->create();
        $viewer->blockedUsers()->attach($blocked);
        $blocker->blockedUsers()->attach($viewer);

        $this->actingAs($viewer)->get(route('readers.all'))->assertInertia(fn (Assert $page) => $page
            ->where('readers.total', 2)->where('readers.data.0.username', $viewer->username)
            ->where('readers.data.1.username', $visible->username)->where('readers.data.1.follow_url', null)
            ->where('routes.verification', route('verification.notice')));
        $this->get(route('readers.index'))->assertInertia(fn (Assert $page) => $page->where('directory.total', 2));
    }

    public function test_row_counts_are_public_and_follow_back_refreshes_mutual_relationships(): void
    {
        $viewer = User::factory()->create();
        $reader = User::factory()->create();
        $reader->following()->attach($viewer);
        CustomList::factory()->for($reader)->create(['is_private' => false]);
        CustomList::factory()->for($reader)->create(['is_private' => true]);
        ReadingList::factory()->for($reader)->create(['status' => 'completed']);
        ReadingList::factory()->for($reader)->create(['status' => 'want_to_read']);
        Review::factory()->for($reader)->create(['body' => 'Public review']);
        Review::factory()->for($reader)->create(['body' => 'Hidden', 'hidden_at' => now()]);
        $url = route('readers.all', ['q' => '@'.$reader->username]);

        $this->actingAs($viewer)->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('readers.data.0.completed_count', 1)->where('readers.data.0.lists_count', 1)
            ->where('readers.data.0.reviews_count', 1)->where('readers.data.0.viewer_follows', false)
            ->where('readers.data.0.follows_viewer', true)->where('readers.data.0.follow_url', route('profiles.follow.store', $reader)));
        $this->from($url)->post(route('profiles.follow.store', $reader))->assertRedirect($url);
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('readers.data.0.viewer_follows', true)->where('readers.data.0.follows_viewer', true)
            ->where('readers.data.0.unfollow_url', route('profiles.follow.destroy', $reader)));
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_full_directory_actions_respect_verification_and_self_follow(bool $enabled): void
    {
        config(['auth.verification.enabled' => $enabled]);
        $viewer = User::factory()->unverified()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($viewer)->get(route('readers.all'));

        $rows = collect($response->inertiaProps('readers.data'))->keyBy('username');
        $this->assertNull($rows[$viewer->username]['follow_url']);
        $this->assertSame($enabled ? null : route('profiles.follow.store', $other), $rows[$other->username]['follow_url']);
    }

    public function test_full_directory_validates_search_and_handles_empty_results(): void
    {
        $this->getJson(route('readers.all', ['q' => ['invalid']]))->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson(route('readers.all', ['q' => str_repeat('x', 101)]))->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->get(route('readers.all', ['q' => 'missing']))->assertInertia(fn (Assert $page) => $page
            ->where('readers.total', 0)->has('readers.data', 0)->where('readers.next_page_url', null));
    }

    public function test_full_directory_query_count_does_not_grow_per_reader(): void
    {
        $viewer = User::factory()->create();
        DB::enableQueryLog();
        $this->actingAs($viewer)->get(route('readers.all'))->assertOk();
        $small = count(DB::getQueryLog());
        DB::disableQueryLog();
        User::factory()->count(25)->create();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('readers.all'))->assertOk();
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual($small + 1, $large);
    }
}
