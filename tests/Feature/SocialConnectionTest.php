<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

        $profileResponse = $this->actingAs(User::factory()->create())
            ->get(route('profiles.show', $profileOwner))
            ->assertOk();

        $this->assertSame(1, $profileResponse->inertiaProps('profile.stats.followers'));
        $this->assertFalse($profileResponse->inertiaProps('profile.is_following'));
        $this->assertTrue($profileResponse->inertiaProps('profile.viewer_authenticated'));

        $this->get(route('profiles.followers', $profileOwner))
            ->assertOk()
            ->assertSeeText('Faithful Reader')
            ->assertSeeText('@faithful_reader');

        $this->get(route('profiles.following', $follower))
            ->assertOk()
            ->assertSeeText('Profile Owner');
    }
}
