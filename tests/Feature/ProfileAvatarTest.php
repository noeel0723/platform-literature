<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_upload_a_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['username' => 'avatar_reader']);

        $this->actingAs($user)->put(route('profiles.update'), $this->profileData($user, [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 400, 400),
        ]))->assertRedirect(route('profiles.show', $user));

        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);

        $this->get(route('profiles.show', $user))
            ->assertOk()
            ->assertSee($user->avatarUrl(), false)
            ->assertSee('profile photo', false);
    }

    public function test_replacing_a_profile_photo_removes_the_previous_file(): void
    {
        Storage::fake('public');
        $oldPath = UploadedFile::fake()->image('old.jpg')->storePublicly('avatars', 'public');
        $user = User::factory()->create([
            'username' => 'replacement_reader',
            'avatar_path' => $oldPath,
        ]);

        $this->actingAs($user)->put(route('profiles.update'), $this->profileData($user, [
            'avatar' => UploadedFile::fake()->image('new.png'),
        ]));

        $user->refresh();

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_user_can_remove_their_profile_photo(): void
    {
        Storage::fake('public');
        $avatarPath = UploadedFile::fake()->image('avatar.webp')->storePublicly('avatars', 'public');
        $user = User::factory()->create([
            'username' => 'remove_avatar_reader',
            'avatar_path' => $avatarPath,
        ]);

        $this->actingAs($user)->put(route('profiles.update'), $this->profileData($user, [
            'remove_avatar' => true,
        ]));

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($avatarPath);
    }

    public function test_profile_photo_must_be_a_supported_image_under_two_megabytes(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profiles.edit'))
            ->put(route('profiles.update'), $this->profileData($user, [
                'avatar' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            ]))
            ->assertRedirect(route('profiles.edit'))
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
    }

    /** @param array<string, mixed> $overrides */
    private function profileData(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'username' => $user->username,
            'location' => $user->location,
            'bio' => $user->bio,
            'favorite_literature_ids' => [],
            'favorite_author_ids' => [],
        ], $overrides);
    }
}
