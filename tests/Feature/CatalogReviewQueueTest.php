<?php

namespace Tests\Feature;

use App\Models\CanonicalWork;
use App\Models\CanonicalWorkIdentifier;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CatalogReviewQueueTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_regular_user_cannot_access_catalog_review_queue(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.catalog-review.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_ambiguous_catalog_matches_and_candidates(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$mapping, $candidate] = $this->ambiguousMapping();

        $this->actingAs($admin)
            ->get(route('admin.catalog-review.index'))
            ->assertOk()
            ->assertSeeText('Catalog review')
            ->assertSeeText($mapping->literature->title)
            ->assertSeeText($candidate->canonical_title)
            ->assertSeeText('Confirm as a separate work');
    }

    public function test_admin_can_confirm_that_an_ambiguous_result_is_separate(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$mapping] = $this->ambiguousMapping();

        $this->actingAs($admin)
            ->patch(route('admin.catalog-review.update', $mapping), [
                'action' => 'keep_separate',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $mapping->refresh();
        $this->assertSame(LiteratureSourceMapping::STATUS_MATCHED, $mapping->mapping_status);
        $this->assertSame('admin_distinct', $mapping->match_method);
        $this->assertNull($mapping->candidate_work_ids);
    }

    public function test_admin_can_merge_an_ambiguous_canonical_group_into_a_suggested_work(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$mapping, $candidate, $current] = $this->ambiguousMapping();
        $identifier = CanonicalWorkIdentifier::factory()->for($current)->create([
            'scheme' => 'comicvine',
            'value' => '4050-1815',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.catalog-review.update', $mapping), [
                'action' => 'merge',
                'canonical_work_id' => $candidate->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $mapping->refresh();
        $this->assertSame($candidate->id, $mapping->canonical_work_id);
        $this->assertSame(LiteratureSourceMapping::STATUS_MATCHED, $mapping->mapping_status);
        $this->assertSame('admin_review', $mapping->match_method);
        $this->assertSame($candidate->id, $identifier->refresh()->canonical_work_id);
        $this->assertSame($mapping->literature_id, $candidate->refresh()->preferred_literature_id);
    }

    public function test_admin_cannot_merge_into_a_work_outside_the_suggested_candidates(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$mapping] = $this->ambiguousMapping();
        $unrelated = CanonicalWork::factory()->create(['type' => 'western-comic']);

        $this->actingAs($admin)
            ->patch(route('admin.catalog-review.update', $mapping), [
                'action' => 'merge',
                'canonical_work_id' => $unrelated->id,
            ])
            ->assertSessionHasErrors('canonical_work_id');

        $this->assertSame(
            LiteratureSourceMapping::STATUS_NEEDS_REVIEW,
            $mapping->refresh()->mapping_status,
        );
    }

    /** @return array{LiteratureSourceMapping, CanonicalWork, CanonicalWork} */
    private function ambiguousMapping(): array
    {
        $literature = Literature::factory()->create([
            'title' => 'Watchmen',
            'type' => 'western-comic',
        ]);
        $current = CanonicalWork::factory()->create([
            'canonical_title' => 'Watchmen',
            'type' => 'western-comic',
        ]);
        $candidateLiterature = Literature::factory()->create([
            'title' => 'Watchmen',
            'type' => 'western-comic',
        ]);
        $candidate = CanonicalWork::factory()->create([
            'preferred_literature_id' => $candidateLiterature->id,
            'canonical_title' => 'Watchmen',
            'type' => 'western-comic',
        ]);
        LiteratureSourceMapping::factory()->create([
            'canonical_work_id' => $candidate->id,
            'literature_id' => $candidateLiterature->id,
            'api_source_id' => $candidateLiterature->api_source_id,
            'mapping_status' => LiteratureSourceMapping::STATUS_MATCHED,
        ]);
        $mapping = LiteratureSourceMapping::factory()->create([
            'canonical_work_id' => $current->id,
            'literature_id' => $literature->id,
            'api_source_id' => $literature->api_source_id,
            'mapping_status' => LiteratureSourceMapping::STATUS_NEEDS_REVIEW,
            'match_method' => 'ambiguous_title',
            'confidence' => 0.45,
            'candidate_work_ids' => [$candidate->id],
        ]);

        return [$mapping->load('literature'), $candidate, $current];
    }
}
