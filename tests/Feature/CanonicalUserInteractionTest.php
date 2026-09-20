<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\CanonicalWork;
use App\Models\CustomList;
use App\Models\CustomListItem;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Models\ReadingList;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CanonicalUserInteractionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rating_through_two_source_siblings_updates_one_preferred_review(): void
    {
        $user = User::factory()->create(['username' => 'canonical_reader']);
        [, $preferred, $sibling] = $this->canonicalPair();

        $this->actingAs($user)->put(route('reviews.update', $sibling), [
            'rating' => 3.5,
            'body' => 'First version.',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($user)->put(route('reviews.update', $preferred), [
            'rating' => 4.5,
            'body' => 'Updated version.',
        ])->assertRedirect();

        $this->assertDatabaseCount('reviews', 1);
        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'literature_id' => $preferred->id,
            'rating' => 4.5,
            'body' => 'Updated version.',
        ]);
        $this->assertDatabaseMissing('reviews', ['literature_id' => $sibling->id]);
        $this->assertTrue(Activity::query()->where('review_id', Review::query()->sole()->id)->get()->every(
            fn (Activity $activity): bool => $activity->literature_id === $preferred->id,
        ));
    }

    public function test_reading_and_completed_status_share_one_preferred_entry(): void
    {
        $user = User::factory()->create();
        [, $preferred, $sibling] = $this->canonicalPair();

        $this->actingAs($user)->put(route('reading-list.update', $sibling), ['status' => 'reading']);
        $this->actingAs($user)->put(route('reading-list.update', $preferred), [
            'status' => 'completed',
            'completed_at' => '2026-09-10',
        ])->assertRedirect();

        $this->assertDatabaseCount('reading_lists', 1);
        $this->assertDatabaseHas('reading_lists', [
            'user_id' => $user->id,
            'literature_id' => $preferred->id,
            'status' => 'completed',
        ]);
        $this->assertSame(1, ReadingList::query()->sole()->logs()->where('event_type', 'completed')->count());
        $this->assertTrue(Activity::query()->get()->every(
            fn (Activity $activity): bool => $activity->literature_id === $preferred->id,
        ));
    }

    public function test_profile_favorites_collapse_siblings_to_the_preferred_literature(): void
    {
        $user = User::factory()->create(['username' => 'favorite_reader']);
        [, $preferred, $sibling] = $this->canonicalPair();

        $this->actingAs($user)->put(route('profiles.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'favorite_literature_ids' => [$sibling->id, $preferred->id],
            'favorite_author_ids' => [],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseCount('user_favorite_literatures', 1);
        $this->assertDatabaseHas('user_favorite_literatures', [
            'user_id' => $user->id,
            'literature_id' => $preferred->id,
            'position' => 1,
        ]);
    }

    public function test_custom_list_cannot_duplicate_siblings_and_stores_the_preferred_literature(): void
    {
        $user = User::factory()->create();
        $list = CustomList::factory()->for($user)->create();
        [$canonical, $preferred, $sibling] = $this->canonicalPair();

        $this->actingAs($user)->post(route('custom-lists.items.store', $list), ['literature_id' => $sibling->id]);
        $this->actingAs($user)->post(route('custom-lists.items.store', $list), ['literature_id' => $preferred->id]);

        $this->assertDatabaseCount('custom_list_items', 1);
        $this->assertDatabaseHas('custom_list_items', [
            'custom_list_id' => $list->id,
            'canonical_work_id' => $canonical->id,
            'literature_id' => $preferred->id,
        ]);
    }

    public function test_unmapped_literature_keeps_its_own_identity(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();

        $this->actingAs($user)->put(route('reviews.update', $literature), [
            'rating' => 4,
            'body' => 'Standalone work.',
        ])->assertRedirect();

        $this->assertDatabaseHas('reviews', ['user_id' => $user->id, 'literature_id' => $literature->id]);
        $this->assertDatabaseHas('reading_lists', ['user_id' => $user->id, 'literature_id' => $literature->id]);
    }

    public function test_audit_command_reports_legacy_review_and_reading_duplicates_without_deleting_them(): void
    {
        $user = User::factory()->create();
        [, $preferred, $sibling] = $this->canonicalPair();
        Review::factory()->create(['user_id' => $user->id, 'literature_id' => $preferred->id, 'body' => 'Preferred review']);
        Review::factory()->create(['user_id' => $user->id, 'literature_id' => $sibling->id, 'body' => 'Conflicting review']);
        ReadingList::factory()->create(['user_id' => $user->id, 'literature_id' => $preferred->id, 'status' => 'completed']);
        ReadingList::factory()->create(['user_id' => $user->id, 'literature_id' => $sibling->id, 'status' => 'reading']);

        $this->artisan('catalog:audit-user-interactions')
            ->expectsOutputToContain('reviews conflict')
            ->expectsOutputToContain('reading_lists conflict')
            ->assertExitCode(1);

        $this->assertDatabaseCount('reviews', 2);
        $this->assertDatabaseCount('reading_lists', 2);
    }

    public function test_audit_fix_safely_canonicalizes_favorites_and_custom_list_items(): void
    {
        $user = User::factory()->create();
        $list = CustomList::factory()->for($user)->create();
        [$canonical, $preferred, $sibling] = $this->canonicalPair();
        $staleCanonical = CanonicalWork::factory()->create(['preferred_literature_id' => $sibling->id]);
        $user->favoriteLiteratures()->attach([
            $preferred->id => ['position' => 1],
            $sibling->id => ['position' => 2],
        ]);
        CustomListItem::factory()->create([
            'custom_list_id' => $list->id,
            'canonical_work_id' => $canonical->id,
            'literature_id' => $preferred->id,
            'position' => 1,
        ]);
        CustomListItem::factory()->create([
            'custom_list_id' => $list->id,
            'canonical_work_id' => $staleCanonical->id,
            'literature_id' => $sibling->id,
            'position' => 2,
        ]);

        $this->artisan('catalog:audit-user-interactions', ['--fix' => true])
            ->expectsOutputToContain('Canonicalized')
            ->assertExitCode(0);

        $this->assertDatabaseCount('user_favorite_literatures', 1);
        $this->assertDatabaseHas('user_favorite_literatures', [
            'user_id' => $user->id,
            'literature_id' => $preferred->id,
            'position' => 1,
        ]);
        $this->assertDatabaseCount('custom_list_items', 1);
        $this->assertDatabaseHas('custom_list_items', [
            'custom_list_id' => $list->id,
            'canonical_work_id' => $canonical->id,
            'literature_id' => $preferred->id,
            'position' => 1,
        ]);
    }

    /** @return array{CanonicalWork, Literature, Literature} */
    private function canonicalPair(): array
    {
        $preferred = Literature::factory()->create(['title' => 'Canonical Edition']);
        $sibling = Literature::factory()->create(['title' => 'Provider Edition']);
        $canonical = CanonicalWork::factory()->create([
            'canonical_title' => 'Canonical Edition',
            'normalized_title' => 'canonical edition',
            'preferred_literature_id' => $preferred->id,
        ]);

        foreach ([$preferred, $sibling] as $literature) {
            LiteratureSourceMapping::factory()->create([
                'canonical_work_id' => $canonical->id,
                'literature_id' => $literature->id,
                'api_source_id' => $literature->api_source_id,
            ]);
        }

        return [$canonical, $preferred, $sibling];
    }
}
