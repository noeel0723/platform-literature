<?php

namespace Tests\Feature;

use App\Models\CanonicalWork;
use App\Models\CustomList;
use App\Models\CustomListItem;
use App\Models\Literature;
use App\Models\LiteratureMetadataOverride;
use App\Models\LiteratureSourceMapping;
use App\Models\ReadingList;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LiteraturePreviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_preview_is_read_only_and_does_not_expose_private_reader_data(): void
    {
        Http::preventStrayRequests();
        $literature = Literature::factory()->create(['title' => 'Preview Work']);
        Review::factory()->for($literature)->create(['body' => 'Another reader private text.']);
        CustomList::factory()->create(['title' => 'Private list', 'is_private' => true]);

        $response = $this->getJson(route('literatures.preview', $literature));

        $response->assertOk()
            ->assertJsonPath('literature.title', 'Preview Work')
            ->assertJsonPath('viewer.authenticated', false)
            ->assertJsonPath('viewer.reading_status', null)
            ->assertJsonPath('literature.review', null)
            ->assertJsonCount(0, 'lists')
            ->assertJsonPath('routes.reading_update', null)
            ->assertJsonPath('routes.reading_destroy', null)
            ->assertJsonPath('literature.review_url', null)
            ->assertJsonPath('csrf_token', null)
            ->assertJsonMissing(['body' => 'Another reader private text.']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseCount('literature_source_mappings', 0);
        Http::assertNothingSent();
    }

    public function test_authenticated_preview_only_returns_the_viewers_review_reading_status_and_lists(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $literature = Literature::factory()->create();
        Review::factory()->for($viewer)->for($literature)->create(['rating' => 4.5, 'body' => 'My own review.']);
        Review::factory()->for($other)->for($literature)->create(['rating' => 1, 'body' => 'Do not expose this review.']);
        ReadingList::factory()->for($viewer)->for($literature)->create(['status' => 'want_to_read']);
        $list = CustomList::factory()->for($viewer)->create(['title' => 'My private list', 'is_private' => true]);
        CustomList::factory()->for($other)->create(['title' => 'Other secret list', 'is_private' => true]);

        $this->actingAs($viewer)->getJson(route('literatures.preview', $literature))
            ->assertOk()
            ->assertJsonPath('viewer.authenticated', true)
            ->assertJsonPath('viewer.reading_status', 'want_to_read')
            ->assertJsonPath('literature.review.rating', 4.5)
            ->assertJsonPath('literature.review.body', 'My own review.')
            ->assertJsonPath('literature.review_url', route('reviews.update', $literature))
            ->assertJsonCount(1, 'lists')
            ->assertJsonPath('lists.0.id', $list->id)
            ->assertJsonPath('lists.0.is_private', true)
            ->assertJsonPath('lists.0.store_url', route('custom-lists.items.store', $list))
            ->assertJsonMissing(['title' => 'Other secret list'])
            ->assertJsonMissing(['body' => 'Do not expose this review.']);
    }

    public function test_preview_uses_the_canonical_representative_curated_metadata_and_sibling_interactions(): void
    {
        $viewer = User::factory()->create();
        [$work, $preferred, $sibling] = $this->canonicalPair();
        LiteratureMetadataOverride::factory()->for($preferred)->create(['title' => 'Curated canonical title']);
        Review::factory()->for($viewer)->for($sibling)->create(['rating' => 3.5]);
        ReadingList::factory()->for($viewer)->for($sibling)->create(['status' => 'completed', 'completed_at' => '2026-09-03']);
        $list = CustomList::factory()->for($viewer)->create();
        $item = CustomListItem::factory()->create([
            'custom_list_id' => $list->id,
            'canonical_work_id' => $work->id,
            'literature_id' => $sibling->id,
        ]);

        $this->actingAs($viewer)->getJson(route('literatures.preview', $sibling))
            ->assertOk()
            ->assertJsonPath('literature.id', $preferred->id)
            ->assertJsonPath('literature.title', 'Curated canonical title')
            ->assertJsonPath('literature.review.rating', 3.5)
            ->assertJsonPath('literature.completed_at', '2026-09-03')
            ->assertJsonPath('viewer.reading_status', 'completed')
            ->assertJsonPath('routes.reading_update', route('reading-list.update', $preferred))
            ->assertJsonPath('lists.0.contains', true)
            ->assertJsonPath('lists.0.destroy_url', route('custom-lists.items.destroy', [$list, $item]));
    }

    public function test_hidden_reviews_are_not_returned_to_the_preview(): void
    {
        $viewer = User::factory()->create();
        $literature = Literature::factory()->create();
        Review::factory()->for($viewer)->for($literature)->create(['body' => 'Hidden text.', 'hidden_at' => now()]);

        $this->actingAs($viewer)->getJson(route('literatures.preview', $literature))
            ->assertOk()->assertJsonPath('literature.review', null);
    }

    public function test_where_to_read_action_requires_an_active_verified_official_link(): void
    {
        [$work, $preferred] = $this->canonicalPair();
        $link = $work->links()->create([
            'provider' => 'Official Reader',
            'url' => 'https://reader.example.test/work',
            'link_type' => 'read',
            'source' => 'curated',
            'is_official' => true,
            'is_active' => true,
            'verified_at' => now(),
        ]);

        $this->getJson(route('literatures.preview', $preferred))
            ->assertOk()->assertJsonPath('routes.where_to_read', route('literatures.show', $preferred).'#where-to-read');

        $link->update(['is_official' => false]);
        $this->getJson(route('literatures.preview', $preferred))
            ->assertOk()->assertJsonPath('routes.where_to_read', null);
    }

    public function test_reading_status_can_be_added_and_removed_as_json_without_redirecting(): void
    {
        $viewer = User::factory()->create();
        [, $preferred, $sibling] = $this->canonicalPair();

        $this->actingAs($viewer)->putJson(route('reading-list.update', $sibling), ['status' => 'want_to_read'])
            ->assertOk()->assertJsonPath('reading_status', 'want_to_read');
        $this->assertDatabaseHas('reading_lists', ['user_id' => $viewer->id, 'literature_id' => $preferred->id, 'status' => 'want_to_read']);

        $this->actingAs($viewer)->deleteJson(route('reading-list.destroy', $sibling))
            ->assertOk()->assertJsonPath('reading_status', null);
        $this->assertDatabaseMissing('reading_lists', ['user_id' => $viewer->id, 'literature_id' => $preferred->id]);
    }

    public function test_json_reading_actions_keep_authentication_and_validation_rules(): void
    {
        $literature = Literature::factory()->create();
        $this->putJson(route('reading-list.update', $literature), ['status' => 'completed'])->assertUnauthorized();

        $viewer = User::factory()->create();
        $this->actingAs($viewer)->putJson(route('reading-list.update', $literature), ['status' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseCount('reading_lists', 0);
    }

    public function test_owner_can_toggle_list_membership_as_json(): void
    {
        $viewer = User::factory()->create();
        $list = CustomList::factory()->for($viewer)->create();
        $literature = Literature::factory()->create();

        $this->actingAs($viewer)->postJson(route('custom-lists.items.store', $list), ['literature_id' => $literature->id])
            ->assertOk()->assertJsonPath('contains', true);
        $item = $list->items()->sole();
        $this->assertSame($literature->id, $item->literature_id);

        $this->actingAs($viewer)->deleteJson(route('custom-lists.items.destroy', [$list, $item]))
            ->assertOk()->assertJsonPath('contains', false);
        $this->assertDatabaseMissing('custom_list_items', ['id' => $item->id]);
    }

    public function test_json_list_actions_cannot_modify_another_readers_private_list(): void
    {
        $viewer = User::factory()->create();
        $list = CustomList::factory()->create(['is_private' => true]);
        $item = CustomListItem::factory()->for($list)->create();

        $this->actingAs($viewer)->postJson(route('custom-lists.items.store', $list), ['literature_id' => $item->literature_id])
            ->assertForbidden();
        $this->actingAs($viewer)->deleteJson(route('custom-lists.items.destroy', [$list, $item]))
            ->assertForbidden();
        $this->assertModelExists($item);
    }

    public function test_browse_cards_receive_the_preview_endpoint(): void
    {
        $literature = Literature::factory()->create();

        $this->get(route('literature.browse'))->assertInertia(fn (Assert $page) => $page
            ->where('literatures.data.0.preview_url', route('literatures.preview', $literature)));
    }

    public function test_missing_preview_returns_not_found(): void
    {
        $this->getJson(route('literatures.preview', ['literature' => 'missing-preview-work']))->assertNotFound();
    }

    /** @return array{CanonicalWork, Literature, Literature} */
    private function canonicalPair(): array
    {
        $preferred = Literature::factory()->create();
        $sibling = Literature::factory()->create();
        $work = CanonicalWork::factory()->create(['preferred_literature_id' => $preferred->id]);

        foreach ([$preferred, $sibling] as $literature) {
            LiteratureSourceMapping::factory()->create([
                'canonical_work_id' => $work->id,
                'literature_id' => $literature->id,
                'api_source_id' => $literature->api_source_id,
            ]);
        }

        return [$work, $preferred, $sibling];
    }
}
