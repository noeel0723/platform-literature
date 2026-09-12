<?php

namespace Tests\Feature;

use App\Models\Literature;
use App\Models\ReadingList;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SocialConnectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_can_follow_and_unfollow_another_reader(): void
    {
        $reader = User::factory()->create();
        $otherReader = User::factory()->create(['username' => 'followed_reader']);

        $this->actingAs($reader)
            ->post(route('profiles.follow.store', $otherReader))
            ->assertRedirect();

        $this->assertDatabaseHas('follows', [
            'follower_id' => $reader->id,
            'followed_id' => $otherReader->id,
        ]);
        $this->assertTrue($reader->isFollowing($otherReader));

        $this->actingAs($reader)
            ->delete(route('profiles.follow.destroy', $otherReader))
            ->assertRedirect();

        $this->assertDatabaseMissing('follows', [
            'follower_id' => $reader->id,
            'followed_id' => $otherReader->id,
        ]);
    }

    public function test_following_the_same_reader_is_idempotent(): void
    {
        $reader = User::factory()->create();
        $otherReader = User::factory()->create();

        $this->actingAs($reader)->post(route('profiles.follow.store', $otherReader));
        $this->actingAs($reader)->post(route('profiles.follow.store', $otherReader));

        $this->assertDatabaseCount('follows', 1);
    }

    public function test_user_cannot_follow_their_own_profile(): void
    {
        $reader = User::factory()->create();

        $this->actingAs($reader)
            ->from(route('profiles.show', $reader))
            ->post(route('profiles.follow.store', $reader))
            ->assertRedirect(route('profiles.show', $reader))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseCount('follows', 0);
    }

    public function test_guest_must_login_before_following_a_reader(): void
    {
        $reader = User::factory()->create();

        $this->post(route('profiles.follow.store', $reader))->assertRedirect(route('login'));
        $this->delete(route('profiles.follow.destroy', $reader))->assertRedirect(route('login'));
        $this->post(route('profiles.block.store', $reader))->assertRedirect(route('login'));
        $this->delete(route('profiles.block.destroy', $reader))->assertRedirect(route('login'));
    }

    public function test_user_cannot_block_their_own_profile(): void
    {
        $reader = User::factory()->create();

        $this->actingAs($reader)
            ->post(route('profiles.block.store', $reader))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseCount('user_blocks', 0);
    }

    public function test_profile_and_connection_pages_show_social_relationships(): void
    {
        $profileOwner = User::factory()->create([
            'name' => 'Profile Owner',
            'username' => 'profile_owner',
        ]);
        $follower = User::factory()->create([
            'name' => 'Faithful Reader',
            'username' => 'faithful_reader',
        ]);
        $profileOwner->followers()->attach($follower);
        ReadingList::factory()->for($follower)->for(Literature::factory())->create(['status' => 'completed']);
        ReadingList::factory()->for($follower)->for(Literature::factory())->create(['status' => 'completed']);
        ReadingList::factory()->for($follower)->for(Literature::factory())->create(['status' => 'want_to_read']);
        ReadingList::factory()->for($follower)->for(Literature::factory())->create(['status' => 'reading']);

        $profileResponse = $this->actingAs(User::factory()->create())
            ->get(route('profiles.show', $profileOwner))
            ->assertOk();

        $this->assertSame(1, $profileResponse->inertiaProps('profile.stats.followers'));
        $this->assertFalse($profileResponse->inertiaProps('profile.is_following'));
        $this->assertTrue($profileResponse->inertiaProps('profile.viewer_authenticated'));

        $this->get(route('profiles.followers', $profileOwner))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Connections')
                ->where('relationship', 'followers')
                ->where('profile.username', 'profile_owner')
                ->has('connections.data', 1)
                ->where('connections.data.0.name', 'Faithful Reader')
                ->where('connections.data.0.username', 'faithful_reader')
                ->where('connections.data.0.completed_count', 2)
                ->where('connections.data.0.readlist_count', 1));

        $this->get(route('profiles.following', $follower))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Connections')
                ->where('relationship', 'following')
                ->has('connections.data', 1)
                ->where('connections.data.0.name', 'Profile Owner'));
    }

    public function test_owner_can_browse_following_followers_and_private_blocked_tabs(): void
    {
        $owner = User::factory()->create(['username' => 'network_owner']);
        $following = User::factory()->create(['username' => 'followed_reader']);
        $follower = User::factory()->create(['username' => 'follower_reader']);
        $blocked = User::factory()->create(['username' => 'blocked_reader']);
        $owner->following()->attach($following);
        $owner->followers()->attach($follower);
        $owner->blockedUsers()->attach($blocked);

        $this->actingAs($owner)
            ->get(route('profiles.connections', [$owner, 'relationship' => 'blocked']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Connections')
                ->where('relationship', 'blocked')
                ->where('canManage', true)
                ->where('counts.following', 1)
                ->where('counts.followers', 1)
                ->where('counts.blocked', 1)
                ->where('connections.data.0.username', 'blocked_reader')
                ->where('navigation.current', 'connections'));

        $this->actingAs(User::factory()->create())
            ->get(route('profiles.connections', [$owner, 'relationship' => 'blocked']))
            ->assertNotFound();
    }

    public function test_blocking_a_reader_removes_mutual_follows_and_can_be_reversed(): void
    {
        $reader = User::factory()->create();
        $otherReader = User::factory()->create();
        $reader->following()->attach($otherReader);
        $otherReader->following()->attach($reader);

        $this->actingAs($reader)
            ->post(route('profiles.block.store', $otherReader))
            ->assertRedirect();

        $this->assertDatabaseHas('user_blocks', [
            'blocker_id' => $reader->id,
            'blocked_id' => $otherReader->id,
        ]);
        $this->assertDatabaseMissing('follows', [
            'follower_id' => $reader->id,
            'followed_id' => $otherReader->id,
        ]);
        $this->assertDatabaseMissing('follows', [
            'follower_id' => $otherReader->id,
            'followed_id' => $reader->id,
        ]);

        $this->actingAs($reader)
            ->delete(route('profiles.block.destroy', $otherReader))
            ->assertRedirect();

        $this->assertDatabaseMissing('user_blocks', [
            'blocker_id' => $reader->id,
            'blocked_id' => $otherReader->id,
        ]);
    }

    public function test_a_reader_cannot_follow_while_either_side_has_an_active_block(): void
    {
        $reader = User::factory()->create();
        $otherReader = User::factory()->create();
        $otherReader->blockedUsers()->attach($reader);

        $this->actingAs($reader)
            ->post(route('profiles.follow.store', $otherReader))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseCount('follows', 0);
    }
}
