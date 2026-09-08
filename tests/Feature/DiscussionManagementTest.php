<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Discussion;
use App\Models\Literature;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DiscussionManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_must_login_to_start_a_discussion(): void
    {
        $literature = Literature::factory()->create();

        $this->post(route('discussions.store', $literature), [
            'title' => 'A question about the ending',
            'discussion_body' => 'How did everyone interpret the final chapter?',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('discussions', 0);
    }

    public function test_user_can_start_a_spoiler_marked_discussion(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();

        $response = $this->actingAs($user)->post(route('discussions.store', $literature), [
            'title' => 'The meaning of the final chapter',
            'discussion_body' => 'I think the final scene changes how we understand the narrator.',
            'discussion_contains_spoiler' => true,
        ]);

        $discussion = Discussion::query()->sole();

        $response->assertRedirect(route('literatures.show', $literature).'#discussion-'.$discussion->id);
        $this->assertDatabaseHas('discussions', [
            'user_id' => $user->id,
            'literature_id' => $literature->id,
            'title' => 'The meaning of the final chapter',
            'contains_spoiler' => true,
        ]);
    }

    public function test_discussion_requires_a_clear_title_and_opening_post(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();

        $this->actingAs($user)->post(route('discussions.store', $literature), [
            'title' => 'No',
            'discussion_body' => 'Too short',
        ])->assertSessionHasErrors(['title', 'discussion_body']);

        $this->assertDatabaseCount('discussions', 0);
    }

    public function test_user_can_comment_and_reply_once_within_a_discussion(): void
    {
        $discussion = Discussion::factory()->create();
        $commenter = User::factory()->create();

        $this->actingAs($commenter)->post(route('discussions.comments.store', $discussion), [
            'comment_body' => 'The imagery supports that interpretation.',
        ])->assertRedirect(route('literatures.show', $discussion->literature).'#discussion-'.$discussion->id);

        $comment = Comment::query()->sole();

        $this->actingAs($commenter)->post(route('discussions.comments.store', $discussion), [
            'comment_body' => 'I noticed the same detail during my reread.',
            'parent_id' => $comment->id,
            'comment_contains_spoiler' => true,
        ])->assertRedirect(route('literatures.show', $discussion->literature).'#discussion-'.$discussion->id);

        $this->assertDatabaseHas('comments', [
            'discussion_id' => $discussion->id,
            'parent_id' => null,
            'body' => 'The imagery supports that interpretation.',
        ]);
        $this->assertDatabaseHas('comments', [
            'discussion_id' => $discussion->id,
            'parent_id' => $comment->id,
            'contains_spoiler' => true,
        ]);
    }

    public function test_reply_parent_must_be_a_top_level_comment_in_the_same_discussion(): void
    {
        $user = User::factory()->create();
        $discussion = Discussion::factory()->create();
        $otherDiscussion = Discussion::factory()->create();
        $foreignComment = Comment::factory()->for($otherDiscussion)->create();

        $this->actingAs($user)->post(route('discussions.comments.store', $discussion), [
            'comment_body' => 'This reply should not cross into another discussion.',
            'parent_id' => $foreignComment->id,
        ])->assertSessionHasErrors('parent_id');

        $parent = Comment::factory()->for($discussion)->create();
        $reply = Comment::factory()->for($discussion)->create(['parent_id' => $parent->id]);

        $this->actingAs($user)->post(route('discussions.comments.store', $discussion), [
            'comment_body' => 'A third nesting level should not be created.',
            'parent_id' => $reply->id,
        ])->assertSessionHasErrors('parent_id');

        $this->assertDatabaseCount('comments', 3);
    }

    public function test_detail_page_shows_discussions_comments_replies_and_spoiler_controls(): void
    {
        $literature = Literature::factory()->create(['title' => 'A Discussed Story']);
        $discussion = Discussion::factory()->for($literature)->for(User::factory()->create(['name' => 'Thread Starter']))->spoiler()->create([
            'title' => 'Discussing the final scene',
            'body' => 'The closing image mirrors the opening chapter.',
        ]);
        $comment = Comment::factory()->for($discussion)->for(User::factory()->create(['name' => 'First Reader']))->create([
            'body' => 'That parallel changed my interpretation.',
        ]);
        $reply = Comment::factory()->for($discussion)->for(User::factory()->create(['name' => 'Second Reader']))->spoiler()->create([
            'parent_id' => $comment->id,
            'body' => 'The narrator confirms it in the epilogue.',
        ]);

        $this->get(route('literatures.show', $literature))
            ->assertOk()
            ->assertSeeText('Discussions')
            ->assertDontSeeText('Increment 3 / Community')
            ->assertSeeText('Discussing the final scene')
            ->assertSeeText('Thread Starter')
            ->assertSeeText('First Reader')
            ->assertSeeText('Second Reader')
            ->assertSeeText('2 comments')
            ->assertSeeText('Reveal spoiler discussion')
            ->assertSeeText('Reveal spoiler reply')
            ->assertSee('id="discussion-body-'.$discussion->id.'" hidden', false)
            ->assertSee('id="comment-body-'.$reply->id.'" hidden', false)
            ->assertSee('data-discussion-thread', false)
            ->assertSee('data-discussion-comment', false)
            ->assertSee('data-local-datetime', false)
            ->assertSeeText('The closing image mirrors the opening chapter.')
            ->assertSeeText('The narrator confirms it in the epilogue.');

    }
}
