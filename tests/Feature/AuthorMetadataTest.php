<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\User;
use App\Services\Literature\AuthorEntityConsolidator;
use App\Services\Literature\AuthorEntityResolver;
use App\Services\Literature\NormalizedAuthor;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class AuthorMetadataTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_editor_is_admin_only_and_public_page_exposes_the_link_only_to_eligible_admins(): void
    {
        Http::preventStrayRequests();
        $author = Author::factory()->create(['image_url' => 'https://example.com/original.jpg']);
        $edit = route('admin.authors.metadata.edit', $author);
        $update = route('admin.authors.metadata.update', $author);
        $this->get($edit)->assertRedirect(route('login'));
        $this->put($update, ['biography_override' => 'No'])->assertRedirect(route('login'));
        $this->get(route('authors.show', $author))->assertInertia(fn (Assert $page) => $page->where('editUrl', null));
        $this->actingAs(User::factory()->create())->get($edit)->assertForbidden();
        $this->put($update, ['biography_override' => 'No'])->assertForbidden();
        $this->get(route('authors.show', $author))->assertInertia(fn (Assert $page) => $page->where('editUrl', null));
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->get($edit)
            ->assertInertia(fn (Assert $page) => $page->component('Admin/AuthorMetadata/Edit')->where('author.name', $author->name)->where('updateUrl', $update));
        $this->get(route('authors.show', $author))->assertInertia(fn (Assert $page) => $page->where('editUrl', $edit));
        $this->assertNull($author->fresh()->biography_override);
    }

    public function test_admin_can_edit_description_and_external_portrait_without_overwriting_api_data_or_identity(): void
    {
        $author = Author::factory()->create(['biography' => 'Original biography', 'image_url' => 'https://example.com/original.jpg']);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->put(route('admin.authors.metadata.update', $author), [
                'biography_override' => '  Curated description  ', 'image_url_override' => 'https://example.com/portrait.jpg',
                'name' => 'Changed name', 'slug' => 'changed-slug', 'biography' => 'Spoofed original', 'image_path' => 'arbitrary.jpg',
            ])->assertRedirect(route('authors.show', $author));
        $fresh = $author->fresh();
        $this->assertSame('Curated description', $fresh->displayBiography());
        $this->assertSame('https://example.com/portrait.jpg', $fresh->displayImageUrl());
        $this->assertSame('Original biography', $fresh->biography);
        $this->assertSame('https://example.com/original.jpg', $fresh->image_url);
        $this->assertSame($author->name, $fresh->name);
        $this->assertSame($author->slug, $fresh->slug);
        $this->assertNull($fresh->image_path);
    }

    public function test_uploaded_portrait_takes_priority_and_replacing_it_cleans_the_previous_file(): void
    {
        Storage::fake('public');
        $author = Author::factory()->create(['image_url' => 'https://example.com/original.jpg']);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->post(route('admin.authors.metadata.update', $author), [
                '_method' => 'PUT', 'image_upload' => UploadedFile::fake()->image('portrait.png', 400, 500),
                'image_url_override' => 'https://example.com/manual.jpg',
            ])->assertRedirect(route('authors.show', $author));
        $firstPath = $author->fresh()->image_path;
        Storage::disk('public')->assertExists($firstPath);
        $this->assertSame(Storage::disk('public')->url($firstPath), $author->fresh()->displayImageUrl());
        $this->post(route('admin.authors.metadata.update', $author), [
            '_method' => 'PUT', 'image_upload' => UploadedFile::fake()->image('new.jpg', 400, 500), 'remove_image_upload' => true,
        ])->assertRedirect();
        $secondPath = $author->fresh()->image_path;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
        $this->assertSame('https://example.com/manual.jpg', $author->fresh()->image_url_override);
    }

    public function test_description_only_update_keeps_existing_photo_and_blank_fields_restore_original_metadata(): void
    {
        Storage::fake('public');
        $path = 'author-portraits/previous.jpg';
        Storage::disk('public')->put($path, 'image');
        $author = Author::factory()->create(['biography' => 'Original', 'image_url' => 'https://example.com/original.jpg', 'biography_override' => 'Custom', 'image_url_override' => 'https://example.com/custom.jpg', 'image_path' => $path]);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->put(route('admin.authors.metadata.update', $author), ['biography_override' => 'Updated'])->assertRedirect();
        $this->assertSame($path, $author->fresh()->image_path);
        Storage::disk('public')->assertExists($path);
        $this->put(route('admin.authors.metadata.update', $author), ['biography_override' => '', 'image_url_override' => '', 'remove_image_upload' => true])->assertRedirect();
        $this->assertSame('Original', $author->fresh()->displayBiography());
        $this->assertSame('https://example.com/original.jpg', $author->fresh()->displayImageUrl());
        Storage::disk('public')->assertMissing($path);
    }

    #[TestWith(['biography_override', ['invalid']])]
    #[TestWith(['image_url_override', 'javascript:alert(1)'])]
    #[TestWith(['image_url_override', 'ftp://example.com/photo.jpg'])]
    #[TestWith(['remove_image_upload', 'invalid'])]
    public function test_invalid_fields_are_rejected_without_modifying_the_author(string $field, mixed $value): void
    {
        $author = Author::factory()->create();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->put(route('admin.authors.metadata.update', $author), [$field => $value])
            ->assertSessionHasErrors($field);
        $this->assertNull($author->fresh()->biography_override);
        $this->assertNull($author->fresh()->image_path);
    }

    public function test_oversized_text_url_and_image_are_rejected(): void
    {
        Storage::fake('public');
        $author = Author::factory()->create();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->put(route('admin.authors.metadata.update', $author), [
                'biography_override' => str_repeat('x', 20001), 'image_url_override' => 'https://example.com/'.str_repeat('x', 4096),
                'image_upload' => UploadedFile::fake()->image('photo.jpg')->size(5121),
            ])->assertSessionHasErrors(['biography_override', 'image_url_override', 'image_upload']);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    #[TestWith(['photo.svg', 'image/svg+xml'])]
    #[TestWith(['photo.jpg', 'application/pdf'])]
    public function test_non_images_and_active_image_formats_are_rejected(string $name, string $mime): void
    {
        Storage::fake('public');
        $author = Author::factory()->create();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->put(route('admin.authors.metadata.update', $author), ['image_upload' => UploadedFile::fake()->create($name, 1, $mime)])
            ->assertSessionHasErrors('image_upload');
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_unverified_admin_respects_optional_verification_without_marking_email_verified(): void
    {
        $author = Author::factory()->create();
        $admin = User::factory()->unverified()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get(route('admin.authors.metadata.edit', $author))->assertRedirect(route('verification.notice'));
        $this->put(route('admin.authors.metadata.update', $author), ['biography_override' => 'No'])->assertRedirect(route('verification.notice'));
        config(['auth.verification.enabled' => false]);
        $this->get(route('admin.authors.metadata.edit', $author))->assertOk();
        $this->put(route('admin.authors.metadata.update', $author), ['biography_override' => 'Allowed'])->assertRedirect();
        $this->assertSame('Allowed', $author->fresh()->displayBiography());
        $this->assertNull($admin->fresh()->email_verified_at);
    }

    public function test_deactivated_admin_cannot_change_author_metadata(): void
    {
        $author = Author::factory()->create();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN, 'deactivated_at' => now()]))
            ->put(route('admin.authors.metadata.update', $author), ['biography_override' => 'No'])->assertRedirect();
        $this->assertNull($author->fresh()->biography_override);
    }

    public function test_api_refresh_keeps_admin_changes_and_public_views_use_the_curated_portrait(): void
    {
        Http::preventStrayRequests();
        Storage::fake('public');
        $author = Author::factory()->create(['name' => 'Test Writer', 'normalized_name' => 'test writer', 'biography' => null, 'image_url' => null,
            'biography_override' => 'Curated description', 'image_path' => 'author-portraits/curated.jpg']);
        $resolved = app(AuthorEntityResolver::class)->resolve('google-books', new NormalizedAuthor(name: 'Test Writer', imageUrl: 'https://example.com/api.jpg', biography: 'API description'));
        $this->assertSame($author->id, $resolved->id);
        $this->assertSame('Curated description', $resolved->displayBiography());
        $imageUrl = Storage::disk('public')->url('author-portraits/curated.jpg');
        $this->get(route('authors.show', $author))->assertInertia(fn (Assert $page) => $page
            ->where('author.image_url', $imageUrl)->where('author.biography', 'Curated description')->where('imageLicenseUrl', null));
        $this->get(route('search.index', ['q' => 'Test Writer']))->assertInertia(fn (Assert $page) => $page->where('authors.0.image_url', $imageUrl));
        $reader = User::factory()->create();
        $reader->favoriteAuthors()->attach($author, ['position' => 0]);
        $this->get(route('profiles.show', $reader))->assertInertia(fn (Assert $page) => $page->where('favoriteAuthors.0.image_url', $imageUrl));
        $this->actingAs($reader)->getJson(route('profiles.favorites.authors', ['q' => 'Test Writer']))->assertJsonPath('results.0.image_url', $imageUrl);
        $this->get(route('profiles.edit'))->assertInertia(fn (Assert $page) => $page->where('favoriteAuthors.0.image_url', $imageUrl));
    }

    public function test_failed_database_save_removes_new_upload_and_retains_original_photo(): void
    {
        Storage::fake('public');
        Exceptions::fake();
        $oldPath = 'author-portraits/original.jpg';
        Storage::disk('public')->put($oldPath, 'image');
        $author = Author::factory()->create(['image_path' => $oldPath]);
        Event::listen('eloquent.updating: '.Author::class, function (): void {
            throw new RuntimeException('Save failed');
        });
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->put(route('admin.authors.metadata.update', $author), ['image_upload' => UploadedFile::fake()->image('new.jpg')])->assertServerError();
        $this->assertSame([$oldPath], Storage::disk('public')->allFiles());
        $this->assertSame($oldPath, $author->fresh()->image_path);
    }

    public function test_photo_removal_cannot_delete_files_outside_the_managed_directory(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profile-banners/unrelated.jpg', 'image');
        $author = Author::factory()->create(['image_path' => 'author-portraits/../profile-banners/unrelated.jpg']);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->put(route('admin.authors.metadata.update', $author), ['remove_image_upload' => true])->assertRedirect();
        Storage::disk('public')->assertExists('profile-banners/unrelated.jpg');
    }

    public function test_duplicate_author_consolidation_preserves_curated_metadata(): void
    {
        $canonical = Author::factory()->create(['name' => 'Test Writer', 'normalized_name' => 'test writer', 'external_entity_id' => 'kg:writer']);
        $duplicate = Author::factory()->create(['name' => 'TEST WRITER', 'normalized_name' => 'test writer',
            'biography_override' => 'Curated description', 'image_path' => 'author-portraits/photo.jpg']);
        app(AuthorEntityConsolidator::class)->consolidate(name: 'Test Writer');
        $this->assertModelMissing($duplicate);
        $this->assertSame('Curated description', $canonical->fresh()->displayBiography());
        $this->assertSame('author-portraits/photo.jpg', $canonical->fresh()->image_path);
    }
}
