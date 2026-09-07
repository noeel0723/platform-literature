<?php

namespace Tests\Feature;

use App\Models\Literature;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ModerationManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_can_report_another_users_review_once(): void
    {
        $reporter = User::factory()->create();
        $review = Review::factory()->create();
        $payload = [
            'target_type' => 'review',
            'target_id' => $review->id,
            'reason' => 'spoiler',
            'details' => 'The ending is visible without a warning.',
        ];

        $firstResponse = $this->actingAs($reporter)->post(route('reports.store'), $payload);
        $secondResponse = $this->actingAs($reporter)->post(route('reports.store'), $payload);

        $firstResponse->assertRedirect()->assertSessionHas('success');
        $secondResponse->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseHas('reports', [
            'reporter_id' => $reporter->id,
            'reportable_type' => Review::class,
            'reportable_id' => $review->id,
            'reason' => 'spoiler',
            'status' => 'pending',
        ]);
    }

    public function test_user_cannot_report_their_own_content(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('reports.store'), [
            'target_type' => 'review',
            'target_id' => $review->id,
            'reason' => 'other',
        ]);

        $response->assertSessionHasErrors('report');
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_regular_user_cannot_access_the_moderation_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.moderation.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_the_pending_moderation_queue(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $review = Review::factory()->create(['body' => 'Reported review body']);
        Report::factory()->create([
            'reportable_type' => Review::class,
            'reportable_id' => $review->id,
            'reason' => 'harassment',
            'details' => 'Please review this message.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.moderation.index'))
            ->assertOk()
            ->assertSee('Moderation queue')
            ->assertSee('Reported review body')
            ->assertSee('Harassment or bullying')
            ->assertSee('Please review this message.');
    }

    public function test_admin_can_hide_reported_review_and_resolve_the_report(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $literature = Literature::factory()->create();
        $review = Review::factory()->for($literature)->create([
            'body' => 'Content that should be hidden',
        ]);
        $report = Report::factory()->create([
            'reportable_type' => Review::class,
            'reportable_id' => $review->id,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.moderation.update', $report), [
            'action' => 'hide',
            'resolution_note' => 'Confirmed violation.',
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'hidden_by' => $admin->id,
        ]);
        $this->assertNotNull($review->refresh()->hidden_at);
        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'resolved',
            'resolved_by' => $admin->id,
            'resolution_note' => 'Confirmed violation.',
        ]);
        $this->get(route('literatures.show', $literature))
            ->assertOk()
            ->assertDontSee('Content that should be hidden');
    }

    public function test_admin_can_deactivate_a_reported_user_and_block_their_access(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $target = User::factory()->create(['email' => 'inactive@example.test']);
        $report = Report::factory()->create([
            'reportable_type' => User::class,
            'reportable_id' => $target->id,
        ]);

        $this->actingAs($admin)->patch(route('admin.moderation.update', $report), [
            'action' => 'deactivate',
        ])->assertRedirect();

        $this->assertNotNull($target->refresh()->deactivated_at);
        $this->assertSame($admin->id, $target->deactivated_by);

        $this->post(route('logout'));
        $this->post(route('login'), [
            'email' => 'inactive@example.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->actingAs($target)
            ->get(route('profiles.edit'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_promotion_command_grants_the_admin_role(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.test']);

        $this->artisan('literahaven:promote-admin', ['email' => 'owner@example.test'])
            ->expectsOutput('owner@example.test can now access the moderation dashboard.')
            ->assertSuccessful();

        $this->assertSame(User::ROLE_ADMIN, $user->refresh()->role);
    }
}
