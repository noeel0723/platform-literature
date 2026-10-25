<?php

namespace Tests\Feature;

use App\Models\CanonicalWork;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Models\ReadingList;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewDraftContextTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_detail_preview_and_quick_log_share_the_same_canonical_draft_identity_and_revision(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $preferred = Literature::factory()->create(['title' => 'Draft Canonical Work']);
        $sibling = Literature::factory()->create(['title' => 'Draft Canonical Work Source']);
        $work = CanonicalWork::factory()->create(['preferred_literature_id' => $preferred->id]);
        foreach ([$preferred, $sibling] as $literature) {
            LiteratureSourceMapping::factory()->for($work, 'canonicalWork')->for($literature)->create([
                'api_source_id' => $literature->api_source_id,
                'source_external_id' => $literature->external_id,
            ]);
        }
        Review::factory()->for($user)->for($sibling)->create(['rating' => 4.5, 'body' => 'My saved review.']);
        ReadingList::factory()->for($user)->for($sibling)->create(['status' => 'completed', 'completed_at' => '2026-10-24']);
        $preview = $this->actingAs($user)->getJson(route('literatures.preview', $sibling))->assertOk();
        $context = $preview->json('literature.review_draft_context');

        $this->get(route('literatures.show', $sibling))->assertInertia(fn (Assert $page) => $page
            ->where('literature.review_draft_context', $context)
            ->where('reviewDraftUserId', $user->id));
        $this->getJson(route('quick-log.literatures', ['q' => 'Draft Canonical Work']))
            ->assertOk()->assertJsonPath('data.0.review_draft_context', $context)
            ->assertJsonPath('data.0.preview_url', route('literatures.preview', $preferred));

        $this->assertSame($user->id, $context['user_id']);
        $this->assertSame('canonical:'.$work->id, $context['work_key']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $context['revision']);
        $this->assertSame(['user_id', 'work_key', 'revision'], array_keys($context));
        Http::assertNothingSent();
    }

    public function test_changing_review_text_changes_the_revision_even_in_the_same_timestamp(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $literature = Literature::factory()->create();
        $review = Review::factory()->for($user)->for($literature)->create(['body' => 'Original review.']);
        $before = $this->actingAs($user)->getJson(route('literatures.preview', $literature))->json('literature.review_draft_context.revision');
        $timestamp = $review->updated_at->toISOString();

        $review->update(['body' => 'A newer saved review.']);
        $response = $this->getJson(route('literatures.preview', $literature))->assertOk();

        $this->assertSame($timestamp, $review->fresh()->updated_at->toISOString());
        $this->assertNotSame($before, $response->json('literature.review_draft_context.revision'));
        $response->assertJsonPath('literature.review.body', 'A newer saved review.');
    }

    public function test_reading_date_changes_are_part_of_the_draft_revision(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();
        $reading = ReadingList::factory()->for($user)->for($literature)->create(['status' => 'completed', 'completed_at' => '2026-10-20']);
        $before = $this->actingAs($user)->getJson(route('literatures.preview', $literature))->json('literature.review_draft_context.revision');

        $reading->update(['completed_at' => '2026-10-24']);
        $response = $this->getJson(route('literatures.preview', $literature))->assertOk();

        $this->assertNotSame($before, $response->json('literature.review_draft_context.revision'));
        $response->assertJsonPath('literature.completed_at', '2026-10-24');
    }

    public function test_guest_preview_does_not_expose_an_account_draft_context(): void
    {
        $literature = Literature::factory()->create();
        Review::factory()->for($literature)->create();

        $this->getJson(route('literatures.preview', $literature))
            ->assertOk()->assertJsonPath('literature.review_draft_context', null);
        $this->get(route('literatures.show', $literature))->assertInertia(fn (Assert $page) => $page
            ->where('reviewDraftUserId', null)->where('literature.review_draft_context', null));
    }

    public function test_unverified_preview_does_not_enable_draft_writing(): void
    {
        $user = User::factory()->unverified()->create();
        $literature = Literature::factory()->create();

        $this->actingAs($user)->getJson(route('literatures.preview', $literature))
            ->assertOk()->assertJsonPath('literature.review_draft_context', null);
    }
}
