<?php

namespace Tests\Feature;

use App\Models\Literature;
use App\Models\ReadingList;
use App\Models\ReadingLog;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReadingManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_must_login_to_manage_reading_data(): void
    {
        $literature = Literature::factory()->create();

        $this->get(route('diary.index'))->assertRedirect(route('login'));
        $this->put(route('reading-list.update', $literature), [
            'status' => 'reading',
        ])->assertRedirect(route('login'));
    }

    public function test_user_can_add_a_literature_and_record_progress(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();

        $this->actingAs($user)->put(route('reading-list.update', $literature), [
            'status' => 'reading',
            'progress_value' => 45,
            'progress_total' => 300,
            'progress_unit' => 'page',
            'note' => 'Bagian awal menarik.',
        ])->assertRedirect(route('literatures.show', $literature));

        $readingList = ReadingList::query()->firstOrFail();

        $this->assertSame('reading', $readingList->status);
        $this->assertNotNull($readingList->started_at);
        $this->assertSame(45, $readingList->progress?->current_value);
        $this->assertSame(300, $readingList->progress?->total_value);
        $this->assertDatabaseHas('reading_logs', [
            'reading_list_id' => $readingList->id,
            'event_type' => 'added_to_readlist',
            'note' => 'Bagian awal menarik.',
        ]);
    }

    public function test_updating_progress_reuses_the_same_reading_list(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();

        $this->actingAs($user)->put(route('reading-list.update', $literature), [
            'status' => 'reading',
            'progress_value' => 10,
            'progress_total' => 20,
            'progress_unit' => 'chapter',
        ]);

        $this->actingAs($user)->put(route('reading-list.update', $literature), [
            'status' => 'reading',
            'progress_value' => 12,
            'progress_total' => 20,
            'progress_unit' => 'chapter',
        ]);

        $this->assertDatabaseCount('reading_lists', 1);
        $this->assertDatabaseCount('reading_progress', 1);
        $this->assertDatabaseHas('reading_progress', ['current_value' => 12]);
        $this->assertDatabaseHas('reading_logs', ['event_type' => 'progress_updated']);
    }

    public function test_completing_and_rereading_updates_dates_and_progress(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();

        $this->actingAs($user)->put(route('reading-list.update', $literature), [
            'status' => 'completed',
            'progress_value' => 100,
            'progress_unit' => 'percent',
        ]);

        $readingList = ReadingList::query()->firstOrFail();
        $this->assertNotNull($readingList->completed_at);

        $this->actingAs($user)->put(route('reading-list.update', $literature), [
            'status' => 'completed',
            'progress_unit' => 'percent',
            'reread' => true,
        ]);

        $readingList->refresh();
        $this->assertSame('reading', $readingList->status);
        $this->assertNull($readingList->completed_at);
        $this->assertSame(1, $readingList->reread_count);
        $this->assertSame(0, $readingList->progress?->current_value);
        $this->assertDatabaseHas('reading_logs', ['event_type' => 'reread']);
    }

    public function test_progress_cannot_exceed_the_total(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();

        $this->actingAs($user)->from(route('literatures.show', $literature))->put(route('reading-list.update', $literature), [
            'status' => 'reading',
            'progress_value' => 21,
            'progress_total' => 20,
            'progress_unit' => 'chapter',
        ])->assertRedirect(route('literatures.show', $literature))
            ->assertSessionHasErrors('progress_value');

        $this->assertDatabaseCount('reading_lists', 0);
    }

    public function test_diary_only_displays_the_authenticated_users_activity(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownLiterature = Literature::factory()->create(['title' => 'My Private Reading']);
        $otherLiterature = Literature::factory()->create(['title' => 'Another Private Reading']);

        $ownList = ReadingList::factory()->for($user)->for($ownLiterature)->create();
        $otherList = ReadingList::factory()->for($otherUser)->for($otherLiterature)->create();
        ReadingLog::factory()->for($ownList)->create();
        ReadingLog::factory()->for($otherList)->create();

        $this->actingAs($user)->get(route('diary.index'))
            ->assertOk()
            ->assertSee('My Private Reading')
            ->assertDontSee('Another Private Reading');
    }

    public function test_diary_displays_utc_activity_in_the_configured_local_timezone(): void
    {
        config()->set([
            'app.display_timezone' => 'Asia/Makassar',
            'app.display_timezone_label' => 'WITA',
        ]);
        $user = User::factory()->create();
        $readingList = ReadingList::factory()
            ->for($user)
            ->for(Literature::factory())
            ->create();
        ReadingLog::factory()->for($readingList)->create([
            'occurred_at' => Carbon::parse('2026-09-06 04:30:00', 'UTC'),
        ]);

        $this->actingAs($user)->get(route('diary.index'))
            ->assertOk()
            ->assertSee('06/09/2026 12:30 WITA');
    }
}
