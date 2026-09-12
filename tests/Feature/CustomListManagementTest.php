<?php

namespace Tests\Feature;

use App\Models\CanonicalWork;
use App\Models\CustomList;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomListManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_create_update_and_delete_a_list(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('custom-lists.store'), [
            'title' => 'Best Fantasy Novels',
            'description' => 'Personal favorites.',
            'is_private' => false,
            'is_ranked' => true,
        ])->assertRedirect();

        $list = CustomList::query()->firstOrFail();
        $this->actingAs($owner)->put(route('custom-lists.update', $list), [
            'title' => 'Essential Fantasy',
            'description' => 'Updated.',
            'is_private' => true,
            'is_ranked' => false,
        ])->assertRedirect(route('custom-lists.show', $list));

        $this->assertDatabaseHas('custom_lists', [
            'id' => $list->id,
            'title' => 'Essential Fantasy',
            'is_private' => true,
            'is_ranked' => false,
        ]);
        $this->actingAs($owner)->delete(route('custom-lists.destroy', $list))->assertRedirect(route('profiles.lists', $owner));
        $this->assertDatabaseMissing('custom_lists', ['id' => $list->id]);
    }

    public function test_owner_can_directly_add_and_remove_literature_from_the_edit_workflow(): void
    {
        $owner = User::factory()->create();
        $list = CustomList::factory()->for($owner)->create(['is_ranked' => true]);
        $literature = Literature::factory()->create(['title' => 'The Left Hand of Darkness']);

        $this->actingAs($owner)
            ->post(route('custom-lists.items.store', $list), ['literature_id' => $literature->id])
            ->assertRedirect();

        $item = $list->items()->firstOrFail();
        $this->assertSame(1, $item->position);
        $this->actingAs($owner)
            ->get(route('custom-lists.edit', $list))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Lists/Edit')
                ->where('list.is_ranked', true)
                ->where('searchUrl', route('quick-log.literatures'))
                ->where('addUrl', route('custom-lists.items.store', $list))
                ->where('reorderUrl', route('custom-lists.items.reorder', $list))
                ->has('items', 1)
                ->where('items.0.id', $item->id)
                ->where('items.0.literature.title', 'The Left Hand of Darkness')
                ->where('items.0.remove_url', route('custom-lists.items.destroy', [$list, $item])));

        $this->actingAs($owner)
            ->delete(route('custom-lists.items.destroy', [$list, $item]))
            ->assertRedirect();

        $this->assertDatabaseMissing('custom_list_items', ['id' => $item->id]);
    }

    public function test_canonical_work_cannot_be_duplicated_in_the_same_list(): void
    {
        $owner = User::factory()->create();
        $list = CustomList::factory()->for($owner)->create();
        $firstEdition = Literature::factory()->create();
        $secondEdition = Literature::factory()->create();
        $canonical = CanonicalWork::factory()->create(['preferred_literature_id' => $firstEdition->id]);
        LiteratureSourceMapping::factory()->create([
            'canonical_work_id' => $canonical->id,
            'literature_id' => $firstEdition->id,
            'api_source_id' => $firstEdition->api_source_id,
        ]);
        LiteratureSourceMapping::factory()->create([
            'canonical_work_id' => $canonical->id,
            'literature_id' => $secondEdition->id,
            'api_source_id' => $secondEdition->api_source_id,
        ]);

        $this->actingAs($owner)->post(route('custom-lists.items.store', $list), ['literature_id' => $firstEdition->id])->assertRedirect();
        $this->actingAs($owner)->post(route('custom-lists.items.store', $list), ['literature_id' => $secondEdition->id])->assertRedirect();

        $this->assertSame(1, $list->items()->count());
        $this->assertSame($canonical->id, $list->items()->firstOrFail()->canonical_work_id);
    }

    public function test_owner_can_reorder_items_and_other_users_cannot_modify_the_list(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $list = CustomList::factory()->for($owner)->create();
        $first = Literature::factory()->create();
        $second = Literature::factory()->create();
        $this->actingAs($owner)->post(route('custom-lists.items.store', $list), ['literature_id' => $first->id]);
        $this->actingAs($owner)->post(route('custom-lists.items.store', $list), ['literature_id' => $second->id]);
        $items = $list->items()->orderBy('position')->get();

        $this->actingAs($owner)->patch(route('custom-lists.items.reorder', $list), [
            'item_ids' => [$items[1]->id, $items[0]->id],
        ])->assertRedirect();
        $this->assertSame([$items[1]->id, $items[0]->id], $list->items()->orderBy('position')->pluck('id')->all());

        $this->actingAs($intruder)->put(route('custom-lists.update', $list), [
            'title' => 'Hijacked',
            'is_private' => false,
        ])->assertForbidden();
        $this->actingAs($intruder)->delete(route('custom-lists.destroy', $list))->assertForbidden();
    }

    public function test_private_lists_are_visible_only_to_the_owner(): void
    {
        $owner = User::factory()->create();
        $public = CustomList::factory()->for($owner)->create(['is_private' => false]);
        $private = CustomList::factory()->for($owner)->create(['is_private' => true]);

        $this->get(route('custom-lists.show', $public))->assertOk();
        $this->get(route('custom-lists.show', $private))->assertForbidden();
        $this->actingAs($owner)->get(route('custom-lists.show', $private))->assertOk();
    }
}
