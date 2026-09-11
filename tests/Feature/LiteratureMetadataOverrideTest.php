<?php

namespace Tests\Feature;

use App\Models\CanonicalWork;
use App\Models\Literature;
use App\Models\LiteratureMetadataOverride;
use App\Models\LiteratureSourceMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LiteratureMetadataOverrideTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_regular_user_cannot_manage_curated_metadata(): void
    {
        $literature = Literature::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.literatures.metadata.edit', $literature))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->put(route('admin.literatures.metadata.update', $literature), ['title' => 'Changed'])
            ->assertForbidden();
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

        $this->actingAs($admin)
            ->get(route('literatures.show', $literature))
            ->assertOk()
            ->assertSeeText('Curated Title')
            ->assertSeeText('Curated synopsis.')
            ->assertSeeText('Curated');
    }

    public function test_admin_can_reset_all_curated_fields_to_the_api_values(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $literature = Literature::factory()->create(['title' => 'API Title']);
        LiteratureMetadataOverride::factory()->for($literature)->create([
            'edited_by' => $admin->id,
            'title' => 'Curated Title',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.literatures.metadata.update', $literature), ['reset' => '1'])
            ->assertRedirect(route('literatures.show', $literature));

        $this->assertDatabaseMissing('literature_metadata_overrides', [
            'literature_id' => $literature->id,
        ]);
        $this->assertSame('API Title', $literature->fresh()->displayTitle());
    }

    public function test_curated_metadata_follows_the_canonical_work_across_api_records(): void
    {
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
    }
}
