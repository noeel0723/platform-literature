<?php

namespace Tests\Feature;

use App\Models\Literature;
use App\Models\ReadingList;
use App\Models\Review;
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
        $this->delete(route('reading-list.destroy', $literature))
            ->assertRedirect(route('login'));
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
        $this->assertDatabaseHas('reading_logs', [
            'reading_list_id' => $readingList->id,
            'event_type' => 'completed',
        ]);

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

    public function test_user_can_cancel_an_active_reading_status(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();
        ReadingList::factory()->for($user)->for($literature)->create(['status' => 'completed']);

        $response = $this->actingAs($user)->get(route('literatures.show', $literature));

        $response->assertOk();
        $this->assertSame('completed', $response->inertiaProps('viewer.reading_status'));

        $this->actingAs($user)
            ->delete(route('reading-list.destroy', $literature))
            ->assertRedirect(route('literatures.show', $literature))
            ->assertSessionHas('success', 'This literature has been removed from your reading activity.');

        $this->assertDatabaseCount('reading_lists', 0);
    }

    public function test_user_cannot_cancel_another_users_reading_status(): void
    {
        $user = User::factory()->create();
        $readingList = ReadingList::factory()
            ->for(User::factory())
            ->for(Literature::factory())
            ->create(['status' => 'completed']);

        $this->actingAs($user)
            ->delete(route('reading-list.destroy', $readingList->literature));

        $this->assertDatabaseHas('reading_lists', ['id' => $readingList->id]);
    }

    public function test_readlist_action_can_be_added_and_cancelled(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();

        $this->actingAs($user)->put(route('reading-list.update', $literature), [
            'status' => 'want_to_read',
        ]);

        $this->assertDatabaseHas('reading_lists', [
            'user_id' => $user->id,
            'literature_id' => $literature->id,
            'status' => 'want_to_read',
        ]);

        $response = $this->actingAs($user)->get(route('literatures.show', $literature));

        $response->assertOk();
        $this->assertSame('want_to_read', $response->inertiaProps('viewer.reading_status'));

        $this->actingAs($user)->delete(route('reading-list.destroy', $literature));

        $this->assertDatabaseCount('reading_lists', 0);
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

        $completedWithoutRating = Literature::factory()->create(['title' => 'Completed Without Rating']);
        ReadingList::factory()->for($user)->for($completedWithoutRating)->create(['status' => 'completed']);
        Review::factory()->for($user)->for($ownLiterature)->create(['rating' => 4.5]);
        Review::factory()->for($otherUser)->for($otherLiterature)->create(['rating' => 3.5]);

        $this->actingAs($user)->get(route('diary.index'))
            ->assertOk()
            ->assertSee('My Private Reading')
            ->assertDontSee('Another Private Reading')
            ->assertDontSee('Completed Without Rating');
    }

    public function test_diary_exposes_utc_activity_for_browser_localization(): void
    {
        $user = User::factory()->create();
        Review::factory()->for($user)->for(Literature::factory())->create([
            'created_at' => Carbon::parse('2026-09-06 04:30:00', 'UTC'),
            'updated_at' => Carbon::parse('2026-09-06 04:30:00', 'UTC'),
        ]);

        $this->actingAs($user)->get(route('diary.index'))
            ->assertOk()
            ->assertSee('datetime="2026-09-06T04:30:00+00:00"', false)
            ->assertSee('data-local-date-part="month"', false)
            ->assertSee('data-local-date-part="day"', false)
            ->assertSee('data-local-date-part="year"', false);
    }

    public function test_diary_includes_the_authenticated_users_ratings_and_reviews(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownLiterature = Literature::factory()->create(['title' => 'My Rated Literature']);
        $otherLiterature = Literature::factory()->create(['title' => 'Someone Else Rating']);

        Review::factory()->for($user)->for($ownLiterature)->create([
            'rating' => 4.5,
            'body' => 'A memorable reading experience.',
        ]);
        Review::factory()->for($otherUser)->for($otherLiterature)->create([
            'rating' => 3.5,
        ]);

        $this->actingAs($user)->get(route('diary.index'))
            ->assertOk()
            ->assertSeeText('My Rated Literature')
            ->assertSeeText('4.5')
            ->assertSeeText('Written')
            ->assertSee('?review=edit', false)
            ->assertDontSeeText('Someone Else Rating');
    }

    public function test_diary_contains_only_activity_history_and_not_the_readlist_collection(): void
    {
        $user = User::factory()->create();
        $savedLiterature = Literature::factory()->create(['title' => 'Private Saved Title']);
        ReadingList::factory()->for($user)->for($savedLiterature)->create(['status' => 'want_to_read']);

        $this->actingAs($user)->get(route('diary.index'))
            ->assertOk()
            ->assertSeeText('Activity history')
            ->assertSee('data-diary-activity-history', false)
            ->assertDontSeeText('Private Saved Title')
            ->assertDontSeeText('Want to read')
            ->assertDontSee('<h2 class="font-serif text-3xl font-bold text-ink-950">Readlist</h2>', false);
    }
}
