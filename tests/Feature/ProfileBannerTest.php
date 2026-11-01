<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProfileBannerTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[TestWith(['jpg'])]
    #[TestWith(['png'])]
    #[TestWith(['webp'])]
    public function test_user_can_upload_a_banner_and_its_position_is_publicly_presented(string $extension): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['username' => 'banner_reader']);

        $this->actingAs($user)->put(route('profiles.update'), $this->profileData($user, [
            'banner' => UploadedFile::fake()->image('banner.'.$extension, 1500, 500),
            'banner_position_x' => 35,
            'banner_position_y' => 70,
        ]))->assertRedirect(route('profiles.show', $user));

        $user->refresh();
        $this->assertStringStartsWith('profile-banners/', $user->banner_path);
        Storage::disk('public')->assertExists($user->banner_path);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'banner_position_x' => 35, 'banner_position_y' => 70]);

        $this->get(route('profiles.edit'))->assertInertia(fn ($page) => $page
            ->where('profile.has_banner', true)
            ->where('profile.banner_url', $user->bannerUrl())
            ->where('profile.banner_position_x', 35));

        auth()->logout();
        $this->get(route('profiles.show', $user))->assertInertia(fn ($page) => $page
            ->where('profile.banner_url', $user->bannerUrl())
            ->where('profile.banner_position_x', 35)
            ->where('profile.banner_position_y', 70)
            ->where('profile.is_owner', false));
    }

    public function test_replacing_a_banner_removes_only_its_previous_file(): void
    {
        Storage::fake('public');
        $oldPath = UploadedFile::fake()->image('old.jpg')->storePublicly('profile-banners', 'public');
        $otherPath = UploadedFile::fake()->image('other.jpg')->storePublicly('profile-banners', 'public');
        $user = User::factory()->create(['username' => 'banner_reader', 'banner_path' => $oldPath]);
        $other = User::factory()->create(['banner_path' => $otherPath]);

        $this->actingAs($user)->put(route('profiles.update'), $this->profileData($user, [
            'banner' => UploadedFile::fake()->image('new.jpg'),
            'user_id' => $other->id,
            'banner_path' => $otherPath,
        ]))->assertRedirect();

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($user->fresh()->banner_path);
        Storage::disk('public')->assertExists($otherPath);
        $this->assertSame($otherPath, $other->fresh()->banner_path);
    }

    public function test_banner_can_be_repositioned_without_uploading_it_again(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('banner.jpg')->storePublicly('profile-banners', 'public');
        $user = User::factory()->create(['username' => 'banner_reader', 'banner_path' => $path]);

        $this->actingAs($user)->put(route('profiles.update'), $this->profileData($user, [
            'banner_position_x' => 0, 'banner_position_y' => 100,
        ]))->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'banner_path' => $path, 'banner_position_x' => 0, 'banner_position_y' => 100]);
        Storage::disk('public')->assertExists($path);
    }

    public function test_removing_a_banner_clears_its_file_and_resets_position(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('banner.jpg')->storePublicly('profile-banners', 'public');
        $user = User::factory()->create(['username' => 'banner_reader', 'banner_path' => $path, 'banner_position_x' => 10, 'banner_position_y' => 20]);

        $this->actingAs($user)->put(route('profiles.update'), $this->profileData($user, ['remove_banner' => true]))->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'banner_path' => null, 'banner_position_x' => 50, 'banner_position_y' => 50]);
        Storage::disk('public')->assertMissing($path);
        $this->assertNull($user->fresh()->bannerUrl());
    }

    #[TestWith(['text'])]
    #[TestWith(['svg'])]
    #[TestWith(['oversized'])]
    #[TestWith(['dimensions'])]
    public function test_invalid_banners_are_rejected_without_changing_the_current_banner(string $case): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('existing.jpg')->storePublicly('profile-banners', 'public');
        $user = User::factory()->create(['username' => 'banner_reader', 'banner_path' => $path]);
        $file = match ($case) {
            'text' => UploadedFile::fake()->create('banner.txt', 10, 'text/plain'),
            'svg' => UploadedFile::fake()->createWithContent('banner.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"></svg>'),
            'oversized' => UploadedFile::fake()->image('large.jpg')->size(5121),
            'dimensions' => UploadedFile::fake()->image('wide.jpg', 6001, 10),
        };

        $this->actingAs($user)->from(route('profiles.edit'))->put(route('profiles.update'), $this->profileData($user, ['banner' => $file]))
            ->assertRedirect(route('profiles.edit'))->assertSessionHasErrors('banner');

        $this->assertSame($path, $user->fresh()->banner_path);
        Storage::disk('public')->assertExists($path);
        $this->assertCount(1, Storage::disk('public')->allFiles('profile-banners'));
    }

    #[TestWith([-1])]
    #[TestWith([101])]
    #[TestWith(['invalid'])]
    public function test_banner_positions_must_be_integers_between_zero_and_one_hundred(mixed $position): void
    {
        $user = User::factory()->create(['username' => 'banner_reader']);

        $this->actingAs($user)->put(route('profiles.update'), $this->profileData($user, [
            'banner_position_x' => $position, 'banner_position_y' => $position,
        ]))->assertSessionHasErrors(['banner_position_x', 'banner_position_y']);

        $this->assertSame(50, $user->fresh()->banner_position_x);
    }

    public function test_profile_update_without_banner_fields_preserves_the_existing_banner(): void
    {
        $user = User::factory()->create(['username' => 'banner_reader', 'banner_path' => 'profile-banners/existing.jpg', 'banner_position_x' => 15]);

        $this->actingAs($user)->put(route('profiles.update'), $this->profileData($user))->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'banner_path' => 'profile-banners/existing.jpg', 'banner_position_x' => 15]);
    }

    public function test_guests_and_unverified_users_cannot_change_a_banner(): void
    {
        $user = User::factory()->unverified()->create(['username' => 'banner_reader']);

        $this->put(route('profiles.update'), $this->profileData($user, ['remove_banner' => true]))->assertRedirect(route('login'));
        $this->actingAs($user)->put(route('profiles.update'), $this->profileData($user, ['remove_banner' => true]))->assertRedirect(route('verification.notice'));

        $this->assertNull($user->fresh()->banner_path);
    }

    public function test_profile_without_a_banner_keeps_null_banner_and_default_position(): void
    {
        $user = User::factory()->create();

        $this->get(route('profiles.show', $user))->assertInertia(fn ($page) => $page
            ->where('profile.banner_url', null)
            ->where('profile.banner_position_x', 50)
            ->where('profile.banner_position_y', 50));
    }

    /** @param array<string, mixed> $overrides */
    private function profileData(User $user, array $overrides = []): array
    {
        return [...[
            'name' => $user->name,
            'username' => $user->username,
            'location' => $user->location,
            'bio' => $user->bio,
            'favorite_literature_ids' => [],
            'favorite_author_ids' => [],
        ], ...$overrides];
    }
}
