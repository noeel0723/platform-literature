<?php

namespace Tests\Feature;

use App\Models\CustomList;
use App\Models\Literature;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ReadersPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_directory_shows_active_readers_with_public_counts_and_ordered_favorites(): void
    {
        Http::preventStrayRequests();
        $reader = User::factory()->create(['name' => 'A Reader', 'username' => 'reader', 'avatar_path' => 'avatars/reader.webp']);
        $follower = User::factory()->create(['name' => 'Z Follower']);
        $inactive = User::factory()->create(['deactivated_at' => now()]);
        $reader->followers()->attach([$follower->id, $inactive->id]);
        $favorites = Literature::factory()->count(5)->create(['type' => 'novel', 'original_title' => null]);
        foreach ($favorites as $position => $literature) {
            $reader->favoriteLiteratures()->attach($literature, ['position' => 4 - $position]);
        }
        Review::factory()->for($reader)->create(['body' => 'A public review.']);
        Review::factory()->for($reader)->create(['body' => 'Hidden review.', 'hidden_at' => now()]);
        Review::factory()->for($reader)->create(['body' => '   ']);

        $response = $this->get(route('readers.index'))->assertInertia(fn (Assert $page) => $page
            ->component('Readers/Index')
            ->where('readers.total', 2)
            ->where('readers.data.0.username', 'reader')
            ->where('readers.data.0.followers_count', 1)
            ->where('readers.data.0.reviews_count', 1)
            ->where('readers.data.0.favorites.0.id', $favorites[4]->id)
            ->where('readers.data.0.favorites.3.id', $favorites[1]->id)
            ->has('readers.data.0.favorites', 4)
            ->where('readers.data.0.follow_url', null)
            ->where('readers.data.0.viewer_follows', false));

        $row = $response->inertiaProps('readers.data.0');
        $this->assertSame(route('profiles.show', $reader), $row['url']);
        $this->assertStringEndsWith('/storage/avatars/reader.webp', $row['avatar_url']);
        foreach (['email', 'password', 'role', 'email_verified_at', 'bio', 'remember_token'] as $privateField) {
            $this->assertArrayNotHasKey($privateField, $row);
        }
        preg_match('/data-react-header-props>(.*?)<\/script>/s', $response->getContent(), $matches);
        $header = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(route('readers.index'), $header['routes']['readers']);
        Http::assertNothingSent();
    }

    public function test_directory_searches_names_and_usernames_and_normalizes_at_prefix(): void
    {
        User::factory()->create(['name' => 'Alex Bookworm', 'username' => 'quiet_reader']);
        User::factory()->create(['name' => 'Other Reader', 'username' => 'alex_reads']);
        User::factory()->create(['name' => 'Unrelated Reader', 'username' => 'unrelated']);

        $this->get(route('readers.index', ['q' => '  aLeX  ']))->assertInertia(fn (Assert $page) => $page
            ->where('query', 'aLeX')->where('readers.total', 2));
        $this->get(route('readers.index', ['q' => '@quiet_reader']))->assertInertia(fn (Assert $page) => $page
            ->where('query', 'quiet_reader')->where('readers.total', 1)->where('readers.data.0.username', 'quiet_reader'));
        $this->get(route('readers.index', ['q' => "' OR 1=1 --"]))->assertInertia(fn (Assert $page) => $page->has('readers.data', 0));
    }

    public function test_directory_is_limited_to_the_five_readers_with_the_most_followers(): void
    {
        $readers = User::factory()->count(7)->create(['name' => 'Matching Reader']);
        $followers = User::factory()->count(7)->create(['name' => 'Follower']);
        foreach ($readers as $index => $reader) {
            $reader->followers()->attach($followers->take($index + 1)->modelKeys());
        }
        Review::factory()->for($readers[0])->create();

        $response = $this->get(route('readers.index'))->assertInertia(fn (Assert $page) => $page
            ->where('readers.total', 5)->has('readers.data', 5)
            ->where('readers.data.0.username', $readers[6]->username)
            ->where('readers.data.4.username', $readers[2]->username)
            ->missing('readers.next_page_url'));
        $this->assertSame([7, 6, 5, 4, 3], collect($response->inertiaProps('readers.data'))->pluck('followers_count')->all());
        $this->get(route('readers.index', ['q' => 'Matching', 'page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->where('query', 'Matching')->where('readers.total', 5)
            ->where('readers.data.0.username', $readers[6]->username));
        $this->get(route('readers.index', ['q' => '@'.$readers[0]->username]))->assertInertia(fn (Assert $page) => $page
            ->where('readers.total', 1)->where('readers.data.0.username', $readers[0]->username));
    }

    public function test_empty_directory_and_unmatched_search_return_empty_paginators(): void
    {
        $this->get(route('readers.index'))->assertInertia(fn (Assert $page) => $page->where('readers.total', 0)->has('readers.data', 0));
        User::factory()->create(['name' => 'Existing Reader']);
        $this->get(route('readers.index', ['q' => 'NotPresentHere']))->assertInertia(fn (Assert $page) => $page->where('query', 'NotPresentHere')->where('readers.total', 0));
    }

    public function test_invalid_search_parameters_are_rejected(): void
    {
        $this->getJson(route('readers.index', ['q' => ['invalid']]))->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->getJson(route('readers.index', ['q' => str_repeat('x', 101)]))->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    public function test_readers_blocked_in_either_direction_are_excluded_even_for_unverified_viewers(): void
    {
        $viewer = User::factory()->unverified()->create();
        $blocked = User::factory()->create();
        $blocker = User::factory()->create();
        $visible = User::factory()->create();
        $viewer->blockedUsers()->attach($blocked);
        $blocker->blockedUsers()->attach($viewer);

        $response = $this->actingAs($viewer)->get(route('readers.index'))->assertInertia(fn (Assert $page) => $page->where('readers.total', 2));
        $this->assertEqualsCanonicalizing([$viewer->username, $visible->username], collect($response->inertiaProps('readers.data'))->pluck('username')->all());
    }

    public function test_follow_back_and_unfollow_use_existing_routes_and_refresh_relationships(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $other->following()->attach($viewer);
        $url = route('readers.index', ['q' => $other->username]);

        $this->actingAs($viewer)->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('readers.data.0.viewer_follows', false)->where('readers.data.0.follows_viewer', true)
            ->where('readers.data.0.follow_url', route('profiles.follow.store', $other)));
        $this->from($url)->post(route('profiles.follow.store', $other))->assertRedirect($url);
        $this->assertDatabaseHas('follows', ['follower_id' => $viewer->id, 'followed_id' => $other->id]);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('readers.data.0.viewer_follows', true)->where('readers.data.0.follows_viewer', true));
        $this->from($url)->delete(route('profiles.follow.destroy', $other))->assertRedirect($url);
        $this->assertDatabaseMissing('follows', ['follower_id' => $viewer->id, 'followed_id' => $other->id]);
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_follow_controls_respect_optional_verification_and_never_allow_self_follow(bool $enabled): void
    {
        config()->set('auth.verification.enabled', $enabled);
        $viewer = User::factory()->unverified()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($viewer)->get(route('readers.index'));
        $rows = collect($response->inertiaProps('readers.data'))->keyBy('username');
        $this->assertSame($enabled ? null : route('profiles.follow.store', $other), $rows[$other->username]['follow_url']);
        $this->assertNull($rows[$viewer->username]['follow_url']);
        $this->assertTrue($rows[$viewer->username]['is_self']);
        $this->assertSame($enabled ? route('verification.notice') : null, $response->inertiaProps('routes.verification'));
    }

    public function test_directory_eager_loads_favorites_and_relationships_without_queries_per_reader(): void
    {
        $viewer = User::factory()->create();
        $first = User::factory()->create();
        $favorites = Literature::factory()->count(4)->create();
        $favoritePositions = $favorites->mapWithKeys(fn (Literature $literature, int $position): array => [$literature->id => ['position' => $position]])->all();
        $first->favoriteLiteratures()->attach($favoritePositions);
        DB::enableQueryLog();
        $this->actingAs($viewer)->get(route('readers.index'))->assertOk();
        $smallCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        User::factory()->count(18)->create()->each(fn (User $reader) => $reader->favoriteLiteratures()->attach($favoritePositions));
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->get(route('readers.index'))->assertOk();
        $largeCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(5, $response->inertiaProps('readers.data'));
        $this->assertLessThanOrEqual($smallCount + 1, $largeCount, 'Discovery query count must not grow per reader.');
    }

    public function test_admins_have_their_own_section_with_public_list_counts_and_no_reader_duplicates(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'username' => 'official_account']);
        $reader = User::factory()->create(['username' => 'regular_reader']);
        User::factory()->create(['role' => User::ROLE_ADMIN, 'deactivated_at' => now()]);
        CustomList::factory()->for($admin)->create(['is_private' => false]);
        CustomList::factory()->for($admin)->create(['is_private' => true]);

        $this->get(route('readers.index'))->assertInertia(fn (Assert $page) => $page
            ->where('admins.total', 1)->where('admins.data.0.username', 'official_account')
            ->where('admins.data.0.is_official', true)->where('admins.data.0.lists_count', 1)
            ->where('admins.data.0.avatar_url', asset('literahaven-logo.png'))
            ->where('readers.total', 1)->where('readers.data.0.username', $reader->username)
            ->missing('admins.data.0.favorites')->missing('admins.data.0.reviews_count')->missing('admins.data.0.email'));
        $this->get(route('readers.index', ['q' => '@official_account']))->assertInertia(fn (Assert $page) => $page
            ->where('admins.total', 1)->where('readers.total', 0));
    }

    public function test_admin_section_preserves_follow_state_and_hides_blocked_admins(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $reader = User::factory()->create();
        $reader->following()->attach($admin);
        $this->actingAs($reader)->get(route('readers.index'))->assertInertia(fn (Assert $page) => $page
            ->where('admins.data.0.viewer_follows', true)->where('admins.data.0.follow_url', route('profiles.follow.store', $admin)));
        $reader->blockedUsers()->attach($admin);
        $this->get(route('readers.index'))->assertInertia(fn (Assert $page) => $page->where('admins.total', 0));
    }
}
