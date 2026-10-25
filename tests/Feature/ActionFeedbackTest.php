<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\CanonicalWork;
use App\Models\CustomList;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Models\User;
use App\Services\Lists\CustomListManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class ActionFeedbackTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_readlist_add_returns_feedback_and_undo_removes_persisted_activity(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();

        $response = $this->actingAs($user)->putJson(route('reading-list.update', $literature), ['status' => 'want_to_read']);

        $response->assertOk()->assertJsonPath('feedback.message', 'Added to your Readlist.');
        $this->assertDatabaseHas('reading_lists', ['user_id' => $user->id, 'status' => 'want_to_read']);
        $this->postJson($response->json('feedback.undo.url'))->assertOk()->assertJsonPath('feedback.message', 'Action undone.');
        $this->assertDatabaseCount('reading_lists', 0);
        $this->assertDatabaseCount('reading_logs', 0);
        $this->assertDatabaseCount('activities', 0);
    }

    public function test_undo_completion_restores_dates_progress_logs_and_original_activity_times(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $literature = Literature::factory()->create();
        $this->actingAs($user)->putJson(route('reading-list.update', $literature), [
            'status' => 'reading', 'progress_value' => 18, 'progress_total' => 200, 'progress_unit' => 'page', 'note' => 'Original note.',
        ])->assertOk();
        $tables = ['reading_lists', 'reading_progress', 'reading_logs', 'activities'];
        $before = $this->snapshot($tables);
        $this->travel(1)->minutes();
        $response = $this->putJson(route('reading-list.update', $literature), [
            'status' => 'completed', 'progress_value' => 200, 'progress_total' => 200, 'progress_unit' => 'page',
        ]);
        $this->assertDatabaseHas('reading_lists', ['status' => 'completed']);

        $this->postJson($response->json('feedback.undo.url'))->assertOk();

        $this->assertSame($before, $this->snapshot($tables));
    }

    public function test_repeating_an_unchanged_status_does_not_offer_meaningless_undo(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $literature = Literature::factory()->create();
        $this->actingAs($user)->putJson(route('reading-list.update', $literature), ['status' => 'want_to_read'])->assertOk();
        $this->travel(1)->minutes();

        $this->putJson(route('reading-list.update', $literature), ['status' => 'want_to_read'])
            ->assertOk()->assertJsonPath('feedback.undo', null);

        $this->assertDatabaseHas('reading_lists', ['user_id' => $user->id, 'status' => 'want_to_read']);
        $this->assertDatabaseCount('reading_logs', 1);
        $this->assertDatabaseCount('activities', 1);
    }

    public function test_undo_removal_restores_readlist_and_history(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();
        $this->actingAs($user)->putJson(route('reading-list.update', $literature), ['status' => 'want_to_read']);
        $tables = ['reading_lists', 'reading_logs', 'activities'];
        $before = $this->snapshot($tables);
        $response = $this->deleteJson(route('reading-list.destroy', $literature));
        $response->assertOk()->assertJsonPath('feedback.message', 'Removed from your Readlist.');
        $this->assertDatabaseCount('reading_lists', 0);

        $this->postJson($response->json('feedback.undo.url'))->assertOk();

        $this->assertSame($before, $this->snapshot($tables));
    }

    public function test_undo_reread_restores_completion_date_and_reread_count(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $literature = Literature::factory()->create();
        $this->actingAs($user)->putJson(route('reading-list.update', $literature), ['status' => 'completed'])->assertOk();
        $tables = ['reading_lists', 'reading_logs', 'activities'];
        $before = $this->snapshot($tables);
        $this->travel(1)->days();
        $response = $this->putJson(route('reading-list.update', $literature), ['status' => 'reading', 'reread' => true]);
        $response->assertOk()->assertJsonPath('feedback.message', 'Started a reread.');
        $this->assertDatabaseHas('reading_lists', ['status' => 'reading', 'reread_count' => 1]);

        $this->postJson($response->json('feedback.undo.url'))->assertOk();

        $this->assertSame($before, $this->snapshot($tables));
    }

    public function test_canonical_sibling_actions_restore_one_preferred_reading_entry(): void
    {
        $user = User::factory()->create();
        [$preferred, $sibling] = $this->canonicalPair();
        $this->actingAs($user)->putJson(route('reading-list.update', $sibling), ['status' => 'reading']);
        $response = $this->putJson(route('reading-list.update', $preferred), ['status' => 'completed']);

        $this->postJson($response->json('feedback.undo.url'))->assertOk();

        $this->assertDatabaseCount('reading_lists', 1);
        $this->assertDatabaseHas('reading_lists', ['literature_id' => $preferred->id, 'status' => 'reading']);
        $this->assertDatabaseMissing('reading_lists', ['literature_id' => $sibling->id]);
    }

    public function test_custom_list_remove_undo_restores_item_ids_and_rank_order(): void
    {
        $user = User::factory()->create();
        $list = CustomList::factory()->for($user)->create(['is_ranked' => true]);
        $manager = app(CustomListManager::class);
        $first = $manager->add($list, Literature::factory()->create());
        $manager->add($list, Literature::factory()->create());
        $before = $this->snapshot(['custom_list_items']);
        $response = $this->actingAs($user)->deleteJson(route('custom-lists.items.destroy', [$list, $first]));
        $this->assertDatabaseMissing('custom_list_items', ['id' => $first->id]);

        $this->postJson($response->json('feedback.undo.url'))->assertOk();

        $this->assertSame($before, $this->snapshot(['custom_list_items']));
    }

    public function test_custom_list_add_undo_preserves_canonical_duplicate_prevention(): void
    {
        $user = User::factory()->create();
        $list = CustomList::factory()->for($user)->create();
        [$preferred, $sibling] = $this->canonicalPair();
        $response = $this->actingAs($user)->postJson(route('custom-lists.items.store', $list), ['literature_id' => $sibling->id]);
        $this->postJson(route('custom-lists.items.store', $list), ['literature_id' => $preferred->id])
            ->assertOk()->assertJsonPath('feedback.undo', null);
        $this->assertDatabaseCount('custom_list_items', 1);

        $this->postJson($response->json('feedback.undo.url'))->assertOk();

        $this->assertDatabaseCount('custom_list_items', 0);
    }

    public function test_favorite_undo_restores_favorites_without_reverting_profile_changes(): void
    {
        $user = User::factory()->create(['username' => 'favorite_reader']);
        $oldLiterature = Literature::factory()->create();
        $newLiterature = Literature::factory()->create();
        $author = Author::factory()->create();
        $user->favoriteLiteratures()->attach($oldLiterature, ['position' => 1]);
        $user->favoriteAuthors()->attach($author, ['position' => 1]);
        $this->actingAs($user)->put(route('profiles.update'), [
            'name' => 'Updated Reader', 'username' => $user->username, 'location' => 'Makassar',
            'favorite_literature_ids' => [$newLiterature->id], 'favorite_author_ids' => [],
        ])->assertRedirect()->assertSessionHas('action_feedback.message', 'Favorites saved.');
        $url = session('action_feedback.undo.url');

        $this->postJson($url)->assertOk();

        $this->assertDatabaseHas('user_favorite_literatures', ['user_id' => $user->id, 'literature_id' => $oldLiterature->id, 'position' => 1]);
        $this->assertDatabaseMissing('user_favorite_literatures', ['literature_id' => $newLiterature->id]);
        $this->assertDatabaseHas('user_favorite_authors', ['user_id' => $user->id, 'author_id' => $author->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Reader', 'location' => 'Makassar']);
    }

    public function test_profile_save_without_favorite_changes_does_not_offer_undo(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['username' => 'profile_reader']);
        $literature = Literature::factory()->create();
        $user->favoriteLiteratures()->attach($literature, ['position' => 1]);
        $this->travel(1)->minutes();

        $this->actingAs($user)->put(route('profiles.update'), ['name' => 'Updated Reader', 'username' => $user->username, 'favorite_literature_ids' => [$literature->id]])
            ->assertRedirect()->assertSessionHas('action_feedback.message', 'Profile saved.')
            ->assertSessionHas('action_feedback.id');

        $this->assertNull(session('action_feedback.undo'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Reader']);
        $this->assertDatabaseHas('user_favorite_literatures', ['user_id' => $user->id, 'literature_id' => $literature->id]);
    }

    public function test_cache_outage_preserves_successful_action_without_offering_unavailable_undo(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();
        Exceptions::fake();
        Cache::partialMock()->shouldReceive('put')->once()->andThrow(new RuntimeException('Cache unavailable.'));

        $this->actingAs($user)->putJson(route('reading-list.update', $literature), ['status' => 'want_to_read'])
            ->assertOk()->assertJsonPath('feedback.message', 'Added to your Readlist.')->assertJsonPath('feedback.undo', null);

        $this->assertDatabaseHas('reading_lists', ['user_id' => $user->id, 'literature_id' => $literature->id, 'status' => 'want_to_read']);
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_transferred_custom_list_cannot_be_restored_by_the_previous_owner(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $list = CustomList::factory()->for($owner)->create();
        $response = $this->actingAs($owner)->postJson(route('custom-lists.items.store', $list), ['literature_id' => Literature::factory()->create()->id]);
        $list->update(['user_id' => $newOwner->id]);

        $this->postJson($response->json('feedback.undo.url'))->assertForbidden();

        $this->assertDatabaseCount('custom_list_items', 1);
        $this->assertDatabaseHas('custom_lists', ['id' => $list->id, 'user_id' => $newOwner->id]);
    }

    public function test_stale_undo_returns_409_without_overwriting_a_new_reading_status(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();
        $response = $this->actingAs($user)->putJson(route('reading-list.update', $literature), ['status' => 'want_to_read']);
        $this->putJson(route('reading-list.update', $literature), ['status' => 'completed'])->assertOk();

        $this->postJson($response->json('feedback.undo.url'))->assertConflict();

        $this->assertDatabaseHas('reading_lists', ['user_id' => $user->id, 'status' => 'completed']);
    }

    public function test_list_reorder_invalidates_an_older_undo(): void
    {
        $user = User::factory()->create();
        $list = CustomList::factory()->for($user)->create();
        $first = app(CustomListManager::class)->add($list, Literature::factory()->create());
        $response = $this->actingAs($user)->postJson(route('custom-lists.items.store', $list), ['literature_id' => Literature::factory()->create()->id]);
        $secondId = $list->items()->where('id', '!=', $first->id)->value('id');
        $this->patch(route('custom-lists.items.reorder', $list), ['item_ids' => [$secondId, $first->id]])->assertRedirect();

        $this->postJson($response->json('feedback.undo.url'))->assertConflict();

        $this->assertDatabaseHas('custom_list_items', ['id' => $secondId, 'position' => 1]);
        $this->assertDatabaseCount('custom_list_items', 2);
    }

    public function test_undo_cannot_be_used_twice(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();
        $response = $this->actingAs($user)->putJson(route('reading-list.update', $literature), ['status' => 'completed']);
        $url = $response->json('feedback.undo.url');
        $this->postJson($url)->assertOk();

        $this->postJson($url)->assertNotFound();

        $this->assertDatabaseCount('reading_lists', 0);
    }

    public function test_expired_undo_returns_404_and_preserves_saved_action(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $literature = Literature::factory()->create();
        $response = $this->actingAs($user)->putJson(route('reading-list.update', $literature), ['status' => 'completed']);
        $this->travel(91)->seconds();

        $this->postJson($response->json('feedback.undo.url'))->assertNotFound();

        $this->assertDatabaseHas('reading_lists', ['user_id' => $user->id, 'status' => 'completed']);
    }

    public function test_other_user_cannot_undo_an_action_returns_404(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $literature = Literature::factory()->create();
        $response = $this->actingAs($owner)->putJson(route('reading-list.update', $literature), ['status' => 'completed']);

        $this->actingAs($other)->postJson($response->json('feedback.undo.url'))->assertNotFound();

        $this->assertDatabaseHas('reading_lists', ['user_id' => $owner->id, 'status' => 'completed']);
    }

    public function test_unverified_user_cannot_undo_returns_403(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();
        $response = $this->actingAs($user)->putJson(route('reading-list.update', $literature), ['status' => 'completed']);
        $user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($user->fresh())->postJson($response->json('feedback.undo.url'))->assertForbidden();

        $this->assertDatabaseHas('reading_lists', ['status' => 'completed']);
    }

    public function test_invalid_submission_returns_422_without_feedback_or_saved_changes(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();

        $this->actingAs($user)->putJson(route('reading-list.update', $literature), ['status' => 'not-a-status'])
            ->assertUnprocessable()->assertJsonValidationErrors('status')->assertJsonMissingPath('feedback');

        $this->assertDatabaseCount('reading_lists', 0);
        $this->assertDatabaseCount('activities', 0);
    }

    public function test_foreign_list_action_returns_403_without_creating_an_item(): void
    {
        $list = CustomList::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('custom-lists.items.store', $list), ['literature_id' => Literature::factory()->create()->id])
            ->assertForbidden()->assertJsonMissingPath('feedback');

        $this->assertDatabaseCount('custom_list_items', 0);
    }

    public function test_inertia_redirect_shares_ephemeral_action_feedback(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();
        $response = $this->actingAs($user)->put(route('reading-list.update', $literature), ['status' => 'want_to_read']);
        $response->assertRedirect(route('literatures.show', $literature))->assertSessionHas('action_feedback');

        $this->get(route('literatures.show', $literature))->assertInertia(fn (Assert $page) => $page
            ->where('actionFeedback.message', 'Added to your Readlist.')->has('actionFeedback.undo.url'));
    }

    private function snapshot(array $tables): array
    {
        return collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all()])->all();
    }

    private function canonicalPair(): array
    {
        $preferred = Literature::factory()->create();
        $sibling = Literature::factory()->create();
        $work = CanonicalWork::factory()->create(['preferred_literature_id' => $preferred->id]);
        foreach ([$preferred, $sibling] as $literature) {
            LiteratureSourceMapping::factory()->for($work, 'canonicalWork')->for($literature)->create([
                'api_source_id' => $literature->api_source_id, 'source_external_id' => $literature->external_id,
            ]);
        }

        return [$preferred, $sibling];
    }
}
