<?php

namespace Tests\Feature;

use App\Models\CustomList;
use App\Models\CustomListComment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CustomListCommentsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_list_comments_are_paginated_newest_first_without_exposing_private_user_data(): void
    {
        $this->freezeTime();
        $list = CustomList::factory()->create();
        $reader = User::factory()->create();
        $comments = CustomListComment::factory()->count(21)->for($list)->for($reader)->create();

        $response = $this->get(route('custom-lists.show', $list))->assertInertia(fn (Assert $page) => $page
            ->component('Lists/Show')->where('comments.total', 21)->has('comments.data', 20)
            ->where('comments.data.0.id', $comments[20]->id)->where('comments.data.19.id', $comments[1]->id)
            ->where('comments.data.0.user.url', route('profiles.show', $reader))
            ->where('comments.data.0.delete_url', null)->where('commenting.store_url', null)
            ->where('commenting.authenticated', false));

        $this->assertStringContainsString('comments_page=2', $response->inertiaProps('comments.next_page_url'));
        $this->assertStringEndsWith('#comments', $response->inertiaProps('comments.next_page_url'));
        foreach (['email', 'password', 'role', 'remember_token'] as $field) {
            $this->assertArrayNotHasKey($field, $response->inertiaProps('comments.data.0.user'));
        }
        $this->get(route('custom-lists.show', [$list, 'comments_page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->has('comments.data', 1)->where('comments.data.0.id', $comments[0]->id));
    }

    public function test_verified_reader_can_post_trimmed_comments_and_cannot_spoof_the_author_or_list(): void
    {
        $reader = User::factory()->create();
        $other = User::factory()->create();
        $list = CustomList::factory()->create();
        $otherList = CustomList::factory()->create();

        $this->actingAs($reader)->post(route('custom-lists.comments.store', [$list, 'page' => 2]), [
            'body' => "  A thoughtful selection.\nThanks for sharing.  ",
            'user_id' => $other->id, 'custom_list_id' => $otherList->id,
        ])->assertRedirect(route('custom-lists.show', [$list, 'page' => 2]).'#comments');

        $comment = CustomListComment::query()->sole();
        $this->assertSame($reader->id, $comment->user_id);
        $this->assertSame($list->id, $comment->custom_list_id);
        $this->assertSame("A thoughtful selection.\nThanks for sharing.", $comment->body);
    }

    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestWith([['invalid']])]
    public function test_empty_whitespace_or_non_string_comments_are_rejected(mixed $body): void
    {
        $list = CustomList::factory()->create();
        $this->actingAs(User::factory()->create())->from(route('custom-lists.show', $list))
            ->post(route('custom-lists.comments.store', $list), ['body' => $body])
            ->assertRedirect(route('custom-lists.show', $list))->assertSessionHasErrors('body');
        $this->assertSame(0, CustomListComment::query()->count());
    }

    public function test_comments_cannot_exceed_three_thousand_characters(): void
    {
        $list = CustomList::factory()->create();
        $this->actingAs(User::factory()->create())->post(route('custom-lists.comments.store', $list), ['body' => str_repeat('a', 3001)])
            ->assertSessionHasErrors('body');
        $this->assertSame(0, CustomListComment::query()->count());
    }

    public function test_guests_and_unverified_accounts_cannot_post_while_optional_verification_is_respected(): void
    {
        $list = CustomList::factory()->create();
        $reader = User::factory()->unverified()->create();
        $this->post(route('custom-lists.comments.store', $list), ['body' => 'Guest'])->assertRedirect(route('login'));
        $this->actingAs($reader)->post(route('custom-lists.comments.store', $list), ['body' => 'Unverified'])->assertRedirect(route('verification.notice'));
        $this->assertSame(0, CustomListComment::query()->count());
        $this->get(route('custom-lists.show', $list))->assertInertia(fn (Assert $page) => $page
            ->where('commenting.store_url', null)->where('commenting.verification_url', route('verification.notice')));

        config(['auth.verification.enabled' => false]);
        $this->post(route('custom-lists.comments.store', $list), ['body' => 'Allowed without mandatory verification'])->assertRedirect();
        $this->assertSame(1, CustomListComment::query()->count());
        $this->assertNull($reader->fresh()->email_verified_at);
    }

    public function test_private_list_comments_are_visible_and_writable_only_to_the_list_owner(): void
    {
        $owner = User::factory()->create();
        $list = CustomList::factory()->for($owner)->create(['is_private' => true]);
        CustomListComment::factory()->for($list)->for($owner)->create(['body' => 'Private note']);

        $this->get(route('custom-lists.show', $list))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('custom-lists.show', $list))->assertForbidden();
        $this->post(route('custom-lists.comments.store', $list), ['body' => 'Intrusion'])->assertForbidden();
        $this->assertSame(1, $list->comments()->count());
        $this->actingAs($owner)->get(route('custom-lists.show', $list))->assertInertia(fn (Assert $page) => $page
            ->where('comments.data.0.body', 'Private note')->where('commenting.store_url', route('custom-lists.comments.store', $list)));
        $this->post(route('custom-lists.comments.store', $list), ['body' => 'Another private note'])->assertRedirect();
        $this->assertSame(2, $list->comments()->count());
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_blocks_in_either_direction_prevent_posting_and_hide_the_blocked_readers_comments(bool $viewerBlocks): void
    {
        $reader = User::factory()->create();
        $owner = User::factory()->create();
        $list = CustomList::factory()->for($owner)->create();
        CustomListComment::factory()->for($list)->for($owner)->create();
        if ($viewerBlocks) {
            $reader->blockedUsers()->attach($owner);
        } else {
            $owner->blockedUsers()->attach($reader);
        }

        $this->actingAs($reader)->get(route('custom-lists.show', $list))->assertInertia(fn (Assert $page) => $page
            ->where('comments.total', 0)->where('commenting.store_url', null));
        $this->post(route('custom-lists.comments.store', $list), ['body' => 'Not allowed'])->assertForbidden();
        $this->assertSame(1, $list->comments()->count());
    }

    public function test_inactive_comment_authors_are_hidden_and_inactive_list_owners_cannot_receive_new_comments(): void
    {
        $list = CustomList::factory()->create();
        $inactive = User::factory()->create(['deactivated_at' => now()]);
        CustomListComment::factory()->for($list)->for($inactive)->create();
        $this->get(route('custom-lists.show', $list))->assertInertia(fn (Assert $page) => $page->where('comments.total', 0));

        $list->user->update(['deactivated_at' => now()]);
        $this->actingAs(User::factory()->create())->post(route('custom-lists.comments.store', $list), ['body' => 'Not allowed'])->assertForbidden();
        $this->assertSame(1, $list->comments()->count());
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_comment_author_or_list_owner_can_delete_a_comment(bool $asListOwner): void
    {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $list = CustomList::factory()->for($owner)->create();
        $comment = CustomListComment::factory()->for($list)->for($author)->create();

        $this->actingAs($asListOwner ? $owner : $author)->get(route('custom-lists.show', $list))->assertInertia(fn (Assert $page) => $page
            ->where('comments.data.0.delete_url', route('custom-lists.comments.destroy', [$list, $comment])));
        $this->delete(route('custom-lists.comments.destroy', [$list, $comment]))->assertRedirect(route('custom-lists.show', $list).'#comments');
        $this->assertModelMissing($comment);
    }

    public function test_other_readers_cannot_delete_comments_and_cross_list_bindings_are_rejected(): void
    {
        $list = CustomList::factory()->create();
        $otherList = CustomList::factory()->create();
        $comment = CustomListComment::factory()->for($list)->create();

        $this->actingAs(User::factory()->create())->delete(route('custom-lists.comments.destroy', [$list, $comment]))->assertForbidden();
        $this->actingAs($list->user)->delete(route('custom-lists.comments.destroy', [$otherList, $comment]))->assertNotFound();
        $this->assertModelExists($comment);
    }

    public function test_comment_rows_are_cleaned_up_when_their_user_or_list_is_deleted(): void
    {
        $reader = User::factory()->create();
        $first = CustomListComment::factory()->for($reader)->create();
        $reader->delete();
        $this->assertModelMissing($first);

        $second = CustomListComment::factory()->create();
        $second->customList->delete();
        $this->assertModelMissing($second);
    }

    public function test_comment_pagination_preserves_the_literature_items_page(): void
    {
        $list = CustomList::factory()->create();
        CustomListComment::factory()->count(21)->for($list)->create();
        $this->actingAs($list->user)->get(route('custom-lists.show', [$list, 'page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->where('list.items.current_page', 2)
            ->where('commenting.store_url', route('custom-lists.comments.store', [$list, 'page' => 2]))
            ->where('comments.next_page_url', route('custom-lists.show', [$list, 'page' => 2, 'comments_page' => 2]).'#comments'));
    }

    public function test_repeated_comment_posts_are_rate_limited(): void
    {
        $list = CustomList::factory()->create();
        $this->actingAs($list->user);
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->post(route('custom-lists.comments.store', $list), ['body' => 'A comment.'])->assertRedirect();
        }

        $this->post(route('custom-lists.comments.store', $list), ['body' => 'One too many.'])->assertTooManyRequests();
        $this->assertSame(30, $list->comments()->count());
    }
}
