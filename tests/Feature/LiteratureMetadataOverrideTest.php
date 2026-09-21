<?php

namespace Tests\Feature;

use App\Models\CanonicalWork;
use App\Models\Literature;
use App\Models\LiteratureMetadataOverride;
use App\Models\LiteratureSourceMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LiteratureMetadataOverrideTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_regular_user_cannot_manage_curated_metadata(): void
    {
        Storage::fake('public');
        $literature = Literature::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.literatures.metadata.edit', $literature))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('admin.literatures.metadata.update', $literature), [
                'cover_upload' => UploadedFile::fake()->image('cover.jpg', 400, 600),
            ])
            ->assertForbidden();

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_admin_can_create_a_curated_override_without_changing_api_metadata(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $literature = Literature::factory()->create([
            'title' => 'API Title',
            'synopsis' => 'API synopsis.',
            'cover_url' => 'https://images.example.test/api.jpg',
            'backdrop_url' => 'https://images.example.test/api-hero.jpg',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.literatures.metadata.update', $literature), [
                'title' => 'Curated Title',
                'synopsis' => 'Curated synopsis.',
                'cover_url' => 'https://images.example.test/curated.jpg',
                'backdrop_url' => 'https://images.example.test/curated-hero.jpg',
                'source_url' => 'https://example.test/reference',
                'notes' => 'Checked against the publisher page.',
            ])
            ->assertRedirect(route('literatures.show', $literature));

        $this->assertDatabaseHas('literature_metadata_overrides', [
            'literature_id' => $literature->id,
            'edited_by' => $admin->id,
            'title' => 'Curated Title',
            'synopsis' => 'Curated synopsis.',
            'backdrop_url' => 'https://images.example.test/curated-hero.jpg',
        ]);
        $this->assertSame('API Title', $literature->fresh()->getRawOriginal('title'));
        $this->assertSame('API synopsis.', $literature->fresh()->getRawOriginal('synopsis'));
        $this->assertSame(
            'https://images.example.test/curated-hero.jpg',
            $literature->fresh()->load('metadataOverride')->displayBackdropUrl(),
        );
        $this->assertSame(
            'https://images.example.test/curated.jpg',
            $literature->fresh()->load('metadataOverride')->displayCoverUrl(),
        );

        $this->actingAs($admin)
            ->get(route('literatures.show', $literature))
            ->assertOk()
            ->assertSeeText('Curated Title')
            ->assertSeeText('Curated synopsis.')
            ->assertSeeText('Curated');
    }

    public function test_admin_edit_form_offers_upload_and_external_cover_options(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $literature = Literature::factory()->create();
        $coverPath = "literature-covers/literature-{$literature->id}/current-cover.jpg";
        Storage::disk('public')->put($coverPath, 'current cover');
        LiteratureMetadataOverride::factory()->for($literature)->create([
            'cover_path' => $coverPath,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.literatures.metadata.edit', $literature))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSeeText('Upload cover')
            ->assertSeeText('External Cover URL')
            ->assertSeeText('Remove uploaded cover')
            ->assertSeeText('Uploaded cover takes priority over an external cover URL.')
            ->assertSee('URL.createObjectURL', false);
    }

    public function test_admin_can_reset_all_curated_fields_to_the_api_values(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $literature = Literature::factory()->create([
            'title' => 'API Title',
            'cover_url' => 'https://images.example.test/api-cover.jpg',
        ]);
        $coverPath = "literature-covers/literature-{$literature->id}/old-cover.jpg";
        Storage::disk('public')->put($coverPath, 'old cover');
        LiteratureMetadataOverride::factory()->for($literature)->create([
            'edited_by' => $admin->id,
            'title' => 'Curated Title',
            'cover_path' => $coverPath,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.literatures.metadata.update', $literature), ['reset' => '1'])
            ->assertRedirect(route('literatures.show', $literature));

        $this->assertDatabaseMissing('literature_metadata_overrides', [
            'literature_id' => $literature->id,
        ]);
        $this->assertSame('API Title', $literature->fresh()->displayTitle());
        $this->assertSame('https://images.example.test/api-cover.jpg', $literature->fresh()->displayCoverUrl());
        Storage::disk('public')->assertMissing($coverPath);
    }

    public function test_curated_metadata_follows_the_canonical_work_across_api_records(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $hardcover = Literature::factory()->create(['title' => 'Hardcover API Title']);
        $googleBooks = Literature::factory()->create(['title' => 'Google Books API Title']);
        $canonicalWork = CanonicalWork::factory()->create([
            'preferred_literature_id' => $hardcover->id,
        ]);

        foreach ([$hardcover, $googleBooks] as $literature) {
            LiteratureSourceMapping::factory()->create([
                'canonical_work_id' => $canonicalWork->id,
                'literature_id' => $literature->id,
                'api_source_id' => $literature->api_source_id,
                'source_external_id' => $literature->external_id,
            ]);
        }

        $this->actingAs($admin)
            ->put(route('admin.literatures.metadata.update', $hardcover), [
                'title' => 'Stable Curated Title',
                'cover_upload' => UploadedFile::fake()->image('canonical-cover.jpg', 400, 600),
            ])
            ->assertRedirect(route('literatures.show', $hardcover));

        $this->assertDatabaseHas('literature_metadata_overrides', [
            'canonical_work_id' => $canonicalWork->id,
            'title' => 'Stable Curated Title',
        ]);
        $this->assertSame(
            'Stable Curated Title',
            $googleBooks->fresh()->load('sourceMapping.canonicalWork.metadataOverride')->displayTitle(),
        );
        $canonicalOverride = $canonicalWork->fresh()->metadataOverride;
        Storage::disk('public')->assertExists($canonicalOverride->cover_path);
        $this->assertSame(
            Storage::disk('public')->url($canonicalOverride->cover_path),
            $googleBooks->fresh()->load('sourceMapping.canonicalWork.metadataOverride')->displayCoverUrl(),
        );
    }

    public function test_admin_can_upload_a_jpg_cover_without_changing_the_api_cover(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $literature = Literature::factory()->create([
            'cover_url' => 'https://images.example.test/api-cover.jpg',
        ]);
        $canonicalWork = CanonicalWork::factory()->create([
            'preferred_literature_id' => $literature->id,
        ]);
        LiteratureSourceMapping::factory()->create([
            'canonical_work_id' => $canonicalWork->id,
            'literature_id' => $literature->id,
            'api_source_id' => $literature->api_source_id,
            'source_external_id' => $literature->external_id,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.literatures.metadata.update', $literature), [
                'cover_upload' => UploadedFile::fake()->image('cover.jpg', 600, 900),
                'cover_url' => 'https://images.example.test/curated-external.jpg',
            ])
            ->assertRedirect(route('literatures.show', $literature));

        $override = $canonicalWork->fresh()->metadataOverride;
        $this->assertNotNull($override);
        $this->assertSame($canonicalWork->id, $override->canonical_work_id);
        $this->assertStringStartsWith("literature-covers/canonical-{$canonicalWork->id}/", $override->cover_path);
        Storage::disk('public')->assertExists($override->cover_path);
        $this->assertSame(
            Storage::disk('public')->url($override->cover_path),
            $literature->fresh()->load('sourceMapping.canonicalWork.metadataOverride')->displayCoverUrl(),
        );
        $this->assertSame('https://images.example.test/api-cover.jpg', $literature->fresh()->getRawOriginal('cover_url'));
    }

    public function test_admin_can_upload_png_and_webp_covers(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $literature = Literature::factory()->create();

        foreach (['cover.png', 'cover.webp'] as $filename) {
            $this->actingAs($admin)
                ->put(route('admin.literatures.metadata.update', $literature), [
                    'cover_upload' => UploadedFile::fake()->image($filename, 600, 900),
                ])
                ->assertRedirect(route('literatures.show', $literature));

            $coverPath = $literature->fresh()->metadataOverride->cover_path;
            Storage::disk('public')->assertExists($coverPath);
            $this->assertStringEndsWith('.'.pathinfo($filename, PATHINFO_EXTENSION), $coverPath);
        }
    }

    public function test_non_image_cover_upload_is_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $literature = Literature::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.literatures.metadata.edit', $literature))
            ->put(route('admin.literatures.metadata.update', $literature), [
                'cover_upload' => UploadedFile::fake()->create('malware.php', 10, 'application/x-php'),
            ])
            ->assertSessionHasErrors('cover_upload');

        $this->assertDatabaseMissing('literature_metadata_overrides', ['literature_id' => $literature->id]);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_cover_upload_larger_than_five_megabytes_is_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $literature = Literature::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.literatures.metadata.edit', $literature))
            ->put(route('admin.literatures.metadata.update', $literature), [
                'cover_upload' => UploadedFile::fake()->image('cover.jpg', 600, 900)->size(5121),
            ])
            ->assertSessionHasErrors('cover_upload');

        $this->assertDatabaseMissing('literature_metadata_overrides', ['literature_id' => $literature->id]);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_replacing_an_uploaded_cover_deletes_the_old_managed_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $literature = Literature::factory()->create();
        $oldCoverPath = "literature-covers/literature-{$literature->id}/old-cover.jpg";
        Storage::disk('public')->put($oldCoverPath, 'old cover');
        LiteratureMetadataOverride::factory()->for($literature)->create([
            'cover_path' => $oldCoverPath,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.literatures.metadata.update', $literature), [
                'cover_upload' => UploadedFile::fake()->image('replacement.jpg', 600, 900),
            ])
            ->assertRedirect(route('literatures.show', $literature));

        $newCoverPath = $literature->fresh()->metadataOverride->cover_path;
        $this->assertNotSame($oldCoverPath, $newCoverPath);
        Storage::disk('public')->assertMissing($oldCoverPath);
        Storage::disk('public')->assertExists($newCoverPath);
    }

    public function test_removing_an_uploaded_cover_falls_back_to_the_curated_url(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $literature = Literature::factory()->create([
            'cover_url' => 'https://images.example.test/api-cover.jpg',
        ]);
        $coverPath = "literature-covers/literature-{$literature->id}/uploaded-cover.jpg";
        Storage::disk('public')->put($coverPath, 'uploaded cover');
        LiteratureMetadataOverride::factory()->for($literature)->create([
            'cover_path' => $coverPath,
            'cover_url' => 'https://images.example.test/curated-cover.jpg',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.literatures.metadata.update', $literature), [
                'remove_cover_upload' => '1',
                'cover_url' => 'https://images.example.test/curated-cover.jpg',
            ])
            ->assertRedirect(route('literatures.show', $literature));

        $override = $literature->fresh()->metadataOverride;
        $this->assertNull($override->cover_path);
        $this->assertSame('https://images.example.test/curated-cover.jpg', $literature->displayCoverUrl());
        Storage::disk('public')->assertMissing($coverPath);
    }
}
