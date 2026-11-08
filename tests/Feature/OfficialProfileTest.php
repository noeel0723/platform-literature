<?php

namespace Tests\Feature;

use App\Models\CanonicalWork;
use App\Models\CustomList;
use App\Models\CustomListItem;
use App\Models\Literature;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class OfficialProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_header_profile_menu_matches_the_account_role_without_removing_account_controls(bool $isAdmin): void
    {
        $user = User::factory()->create(['role' => $isAdmin ? User::ROLE_ADMIN : User::ROLE_USER]);
        $response = $this->actingAs($user)->get(route('profiles.show', $user))->assertOk();
        preg_match('/data-react-header-props>(.*?)<\/script>/s', $response->getContent(), $matches);
        $props = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

        $expected = $isAdmin
            ? ['Profile', 'Lists', 'Connections']
            : ['Home', 'Profile', 'Stats', 'Activity', 'Literature', 'Diary', 'Reviews', 'Readlist', 'Lists', 'Connections'];
        $this->assertSame($expected, array_column($props['user']['navigation'], 'label'));
        if ($isAdmin) {
            $this->assertSame([
                route('profiles.show', $user), route('profiles.lists', $user), route('profiles.connections', $user),
            ], array_column($props['user']['navigation'], 'url'));
        }
        $this->assertSame(route('profiles.edit'), $props['user']['edit_url']);
        $this->assertSame($isAdmin ? route('admin.moderation.index') : null, $props['user']['moderation_url']);
        $this->assertSame(route('logout'), $props['user']['logout_url']);
    }

    public function test_admin_profile_shows_only_public_list_updates_and_official_statistics(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'banner_path' => 'banners/official.webp', 'banner_position_y' => 30]);
        $public = CustomList::factory()->for($admin)->create(['is_private' => false]);
        CustomList::factory()->for($admin)->create(['is_private' => true, 'title' => 'Private draft']);
        Review::factory()->for($admin)->create(['body' => 'This is not an official update.']);
        $literature = Literature::factory()->create(['type' => 'manga', 'title' => 'Official Selection']);
        $canonical = CanonicalWork::factory()->create(['preferred_literature_id' => $literature->id]);
        CustomListItem::factory()->for($public)->create(['literature_id' => null, 'canonical_work_id' => $canonical->id]);

        $this->get(route('profiles.show', $admin))->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Official')
            ->where('profile.is_official', true)
            ->where('profile.stats.lists', 1)
            ->where('profile.stats.followers', 0)
            ->where('profile.banner_position_y', 30)
            ->where('profile.is_owner', false)
            ->where('routes.create_list', null)
            ->where('lists.total', 1)
            ->where('lists.data.0.id', $public->id)
            ->where('lists.data.0.edit_url', null)
            ->where('lists.data.0.items_count', 1)
            ->where('lists.data.0.previews.0.title', 'Official Selection')
            ->where('navigation.links.0.label', 'Profile')
            ->where('navigation.links.1.label', 'Updates')
            ->where('navigation.links.2.label', 'Lists')
            ->where('navigation.links.3.label', 'Connections')
            ->has('navigation.links', 4)
            ->missing('favoriteLiteratures')->missing('recentReviews')->missing('readlistPreview')->missing('ratingDistribution')
            ->missing('profile.stats.completed')->missing('profile.stats.reviews'));
    }

    public function test_regular_profiles_keep_the_existing_reading_sections_and_navigation(): void
    {
        $reader = User::factory()->create();
        $this->get(route('profiles.show', $reader))->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')
            ->has('favoriteLiteratures')->has('favoriteAuthors')->has('recentReviews')->has('readlistPreview')->has('ratingDistribution')
            ->where('navigation.links.1.label', 'Stats'));
    }

    public function test_official_profile_paginates_updates_in_newest_updated_order(): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $latest = CustomList::factory()->for($admin)->create(['is_private' => false, 'updated_at' => now()]);
        CustomList::factory()->count(7)->for($admin)->create(['is_private' => false, 'updated_at' => now()->subDay()]);

        $first = $this->get(route('profiles.show', $admin))->assertInertia(fn (Assert $page) => $page
            ->where('lists.total', 8)->where('lists.data.0.id', $latest->id)->has('lists.data', 6));
        $this->get($first->inertiaProps('lists.next_page_url'))->assertInertia(fn (Assert $page) => $page
            ->where('lists.current_page', 2)->has('lists.data', 2));
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_admin_profile_management_controls_follow_existing_verification_requirements(bool $enabled): void
    {
        config()->set('auth.verification.enabled', $enabled);
        $admin = User::factory()->unverified()->create(['role' => User::ROLE_ADMIN]);
        $public = CustomList::factory()->for($admin)->create(['is_private' => false]);
        CustomList::factory()->for($admin)->create(['is_private' => true]);

        $this->actingAs($admin)->get(route('profiles.show', $admin))->assertInertia(fn (Assert $page) => $page
            ->where('profile.is_owner', true)->where('lists.total', 1)
            ->where('routes.edit', $enabled ? null : route('profiles.edit'))
            ->where('routes.create_list', $enabled ? null : route('custom-lists.create'))
            ->where('lists.data.0.edit_url', $enabled ? null : route('custom-lists.edit', $public)));
        $this->assertNull($admin->fresh()->email_verified_at);
    }

    public function test_owner_can_still_manage_private_lists_while_visitors_cannot(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $private = CustomList::factory()->for($admin)->create(['is_private' => true]);
        $this->actingAs($admin)->get(route('profiles.lists', $admin))->assertInertia(fn (Assert $page) => $page
            ->where('isOwner', true)->where('lists.total', 1)->where('navigation.links.2.key', 'lists'));
        $this->get(route('custom-lists.edit', $private))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('custom-lists.edit', $private))->assertForbidden();
        $this->get(route('custom-lists.show', $private))->assertForbidden();
    }

    public function test_official_follow_controls_are_hidden_while_a_block_is_active(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $reader = User::factory()->create();
        $reader->blockedUsers()->attach($admin);
        $this->actingAs($reader)->get(route('profiles.show', $admin))->assertInertia(fn (Assert $page) => $page
            ->where('profile.follow_blocked', true)->where('profile.viewer_authenticated', false)->where('routes.follow', null));
        $this->post(route('profiles.follow.store', $admin))->assertSessionHasErrors('user');
        $this->assertDatabaseMissing('follows', ['follower_id' => $reader->id, 'followed_id' => $admin->id]);
    }
}
