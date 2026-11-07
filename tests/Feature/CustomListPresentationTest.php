<?php

namespace Tests\Feature;

use App\Models\CanonicalWork;
use App\Models\CustomList;
use App\Models\CustomListItem;
use App\Models\Like;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Models\ReadingList;
use App\Models\User;
use App\Services\Accounts\DeleteUserAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CustomListPresentationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_banner_uses_the_first_item_on_every_page_and_changes_after_reordering(): void
    {
        $owner = User::factory()->create();
        $list = CustomList::factory()->for($owner)->create(['is_ranked' => true]);
        $first = Literature::factory()->create(['title' => 'First story', 'backdrop_url' => 'https://example.com/first.jpg']);
        $second = Literature::factory()->create(['title' => 'Second story', 'backdrop_url' => 'https://example.com/second.jpg']);
        $a = CustomListItem::factory()->for($list)->for($first)->create(['position' => 1]);
        $b = CustomListItem::factory()->for($list)->for($second)->create(['position' => 2]);
        for ($position = 3; $position <= 25; $position++) {
            CustomListItem::factory()->for($list)->create(['position' => $position]);
        }
        $this->get(route('custom-lists.show', [$list, 'page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->where('list.hero.title', 'First story')->where('list.hero.backdrop_url', $first->backdrop_url)
            ->where('list.items.total', 25)->has('list.items.data', 1)->where('list.items.data.0.position', 25)
            ->where('list.progress', null));
        $this->actingAs($owner)->patch(route('custom-lists.items.reorder', $list), ['item_ids' => [$b->id, $a->id]])->assertRedirect();
        $this->get(route('custom-lists.show', $list))->assertInertia(fn (Assert $page) => $page
            ->where('list.hero.title', 'Second story')->where('list.items.data.0.id', $b->id));
    }

    public function test_progress_counts_completed_canonical_works_once_and_only_for_the_viewer(): void
    {
        $viewer = User::factory()->create();
        $list = CustomList::factory()->create();
        $first = Literature::factory()->create();
        $edition = Literature::factory()->create();
        $canonical = CanonicalWork::factory()->create(['preferred_literature_id' => $first->id]);
        foreach ([$first, $edition] as $literature) {
            LiteratureSourceMapping::factory()->create(['canonical_work_id' => $canonical->id, 'literature_id' => $literature->id, 'api_source_id' => $literature->api_source_id]);
        }
        $item = CustomListItem::factory()->for($list)->for($first)->create(['canonical_work_id' => $canonical->id, 'position' => 1]);
        foreach ([$first, $edition] as $literature) {
            ReadingList::factory()->for($viewer)->for($literature)->create(['status' => 'completed']);
        }
        foreach (['reading', 'dnf', 'want_to_read'] as $index => $status) {
            $otherItem = CustomListItem::factory()->for($list)->create(['position' => $index + 2]);
            ReadingList::factory()->for($viewer)->create(['literature_id' => $otherItem->literature_id, 'status' => $status]);
            ReadingList::factory()->create(['literature_id' => $otherItem->literature_id, 'status' => 'completed']);
        }
        $this->actingAs($viewer)->get(route('custom-lists.show', $list))->assertInertia(fn (Assert $page) => $page
            ->where('list.progress', ['completed' => 1, 'total' => 4, 'percentage' => 25])
            ->where('list.items.data.0.id', $item->id)->where('list.items.data.0.completed', true)
            ->where('list.items.data.1.completed', false));
    }

    public function test_progress_includes_off_page_items_and_preferred_literature_without_a_mapping(): void
    {
        $viewer = User::factory()->create();
        $list = CustomList::factory()->create();
        for ($position = 1; $position <= 25; $position++) {
            CustomListItem::factory()->for($list)->create(['position' => $position]);
        }
        $last = $list->items()->where('position', 25)->first();
        $preferred = Literature::factory()->create(['cover_url' => 'https://example.com/cover.jpg']);
        $last->canonicalWork->update(['preferred_literature_id' => $preferred->id]);
        $last->update(['literature_id' => null]);
        ReadingList::factory()->for($viewer)->for($preferred)->create(['status' => 'completed']);
        $this->actingAs($viewer)->get(route('custom-lists.show', $list))->assertInertia(fn (Assert $page) => $page
            ->where('list.progress', ['completed' => 1, 'total' => 25, 'percentage' => 4]));
        $this->get(route('custom-lists.show', [$list, 'page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->where('list.progress.completed', 1)->where('list.items.data.0.completed', true)
            ->where('list.items.data.0.literature.id', $preferred->id));
    }

    public function test_empty_list_has_no_banner_and_zero_progress_for_signed_in_readers(): void
    {
        $list = CustomList::factory()->create();
        $this->actingAs(User::factory()->create())->get(route('custom-lists.show', $list))->assertInertia(fn (Assert $page) => $page
            ->where('list.hero', null)->where('list.progress', ['completed' => 0, 'total' => 0, 'percentage' => 0]));
    }

    public function test_percentage_does_not_round_an_incomplete_list_up_to_one_hundred(): void
    {
        $viewer = User::factory()->create();
        $list = CustomList::factory()->create();
        for ($position = 1; $position <= 3; $position++) {
            $item = CustomListItem::factory()->for($list)->create(['position' => $position]);
            if ($position < 3) {
                ReadingList::factory()->for($viewer)->create(['literature_id' => $item->literature_id, 'status' => 'completed']);
            }
        }
        $this->actingAs($viewer)->get(route('custom-lists.show', $list))->assertInertia(fn (Assert $page) => $page
            ->where('list.progress.percentage', 66));
    }

    public function test_canonical_only_first_item_uses_its_preferred_cover_as_banner_fallback(): void
    {
        $list = CustomList::factory()->create();
        $preferred = Literature::factory()->create(['cover_url' => 'https://example.com/cover.jpg']);
        $canonical = CanonicalWork::factory()->create(['preferred_literature_id' => $preferred->id]);
        CustomListItem::factory()->for($list)->create(['canonical_work_id' => $canonical->id, 'literature_id' => null, 'position' => 1]);
        $this->get(route('custom-lists.show', $list))->assertInertia(fn (Assert $page) => $page
            ->where('list.hero.title', $preferred->title)->where('list.hero.cover_url', $preferred->cover_url)
            ->where('list.hero.backdrop_url', null));
    }

    public function test_likes_are_idempotent_use_current_user_and_preserve_pagination(): void
    {
        $list = CustomList::factory()->create();
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $url = route('custom-lists.likes.store', [$list, 'page' => 2, 'comments_page' => 3]);
        $this->actingAs($viewer)->post($url, ['user_id' => $other->id])->assertRedirect(route('custom-lists.show', [$list, 'page' => 2, 'comments_page' => 3]));
        $this->post($url)->assertRedirect();
        $this->assertSame(1, $list->likes()->count());
        $this->assertSame($viewer->id, $list->likes()->sole()->user_id);
        $this->get(route('custom-lists.show', $list))->assertInertia(fn (Assert $page) => $page
            ->where('list.likes_count', 1)->where('list.is_liked', true)->where('list.unlike_url', route('custom-lists.likes.destroy', $list)));
        $this->delete(route('custom-lists.likes.destroy', $list))->assertRedirect();
        $this->delete(route('custom-lists.likes.destroy', $list))->assertRedirect();
        $this->assertSame(0, $list->likes()->count());
    }

    public function test_guest_private_and_unverified_like_permissions_are_enforced(): void
    {
        $list = CustomList::factory()->create();
        $this->post(route('custom-lists.likes.store', $list))->assertRedirect(route('login'));
        $viewer = User::factory()->unverified()->create();
        $this->actingAs($viewer)->post(route('custom-lists.likes.store', $list))->assertRedirect(route('verification.notice'));
        config(['auth.verification.enabled' => false]);
        $this->post(route('custom-lists.likes.store', $list))->assertRedirect();
        $this->assertNull($viewer->fresh()->email_verified_at);
        $list->update(['is_private' => true]);
        $this->post(route('custom-lists.likes.store', $list))->assertForbidden();
        $this->delete(route('custom-lists.likes.destroy', $list))->assertForbidden();
        $this->actingAs($list->user)->post(route('custom-lists.likes.store', $list))->assertRedirect();
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_blocks_prevent_like_actions_in_either_direction(bool $viewerBlocks): void
    {
        $list = CustomList::factory()->create();
        $viewer = User::factory()->create();
        if ($viewerBlocks) {
            $viewer->blockedUsers()->attach($list->user);
        } else {
            $list->user->blockedUsers()->attach($viewer);
        }
        $this->actingAs($viewer)->post(route('custom-lists.likes.store', $list))->assertForbidden();
        $this->delete(route('custom-lists.likes.destroy', $list))->assertForbidden();
        $this->get(route('custom-lists.show', $list))->assertInertia(fn (Assert $page) => $page->where('list.like_url', null));
        $this->assertSame(0, $list->likes()->count());
    }

    public function test_inactive_likes_are_hidden_and_inactive_owner_cannot_receive_likes(): void
    {
        $list = CustomList::factory()->create();
        $inactive = User::factory()->create(['deactivated_at' => now()]);
        $list->likes()->create(['user_id' => $inactive->id]);
        $this->get(route('custom-lists.show', $list))->assertInertia(fn (Assert $page) => $page->where('list.likes_count', 0));
        $list->user->update(['deactivated_at' => now()]);
        $this->actingAs(User::factory()->create())->post(route('custom-lists.likes.store', $list))->assertForbidden();
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_deleting_list_or_its_owner_removes_other_readers_likes_without_touching_catalog(bool $deleteOwner): void
    {
        $list = CustomList::factory()->create();
        $literature = Literature::factory()->create();
        CustomListItem::factory()->for($list)->for($literature)->create();
        $list->likes()->create(['user_id' => User::factory()->create()->id]);
        if ($deleteOwner) {
            app(DeleteUserAccount::class)->delete($list->user);
        } else {
            $list->delete();
        }
        $this->assertSame(0, Like::query()->count());
        $this->assertModelExists($literature);
    }
}
