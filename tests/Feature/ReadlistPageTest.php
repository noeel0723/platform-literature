<?php

namespace Tests\Feature;

use App\Models\Literature;
use App\Models\ReadingList;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReadlistPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_readlist_page_only_contains_want_to_read_titles(): void
    {
        $user = User::factory()->create(['name' => 'Readlist Owner']);
        $saved = Literature::factory()->create(['title' => 'Saved for Later']);
        $reading = Literature::factory()->create(['title' => 'Currently Reading']);
        $completed = Literature::factory()->create(['title' => 'Already Completed']);
        ReadingList::factory()->for($user)->for($saved)->create(['status' => 'want_to_read']);
        ReadingList::factory()->for($user)->for($reading)->create(['status' => 'reading']);
        ReadingList::factory()->for($user)->for($completed)->create(['status' => 'completed']);

        $response = $this->get(route('profiles.readlist', $user))->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Readlist')
            ->where('profile.name', 'Readlist Owner')
            ->where('readlist.total', 1)
            ->where('readlist.data.0.literature.title', 'Saved for Later')
            ->where('isOwner', false)
        );
        $this->assertNotContains('Currently Reading', collect($response->inertiaProps('readlist.data'))->pluck('literature.title'));
        $this->assertNotContains('Already Completed', collect($response->inertiaProps('readlist.data'))->pluck('literature.title'));
    }

    public function test_profile_shows_a_four_item_readlist_preview_and_full_page_link(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $position) {
            ReadingList::factory()
                ->for($user)
                ->for(Literature::factory()->create(['title' => "Saved Title {$position}"]))
                ->create([
                    'status' => 'want_to_read',
                    'updated_at' => now()->subMinutes($position),
                ]);
        }

        $response = $this->get(route('profiles.show', $user))->assertOk();

        $this->assertSame(route('profiles.readlist', $user), $response->inertiaProps('routes.readlist'));
        $this->assertSame(5, $response->inertiaProps('profile.stats.readlist'));
        $this->assertSame(
            ['Saved Title 1', 'Saved Title 2', 'Saved Title 3', 'Saved Title 4'],
            collect($response->inertiaProps('readlistPreview'))->pluck('title')->all(),
        );
    }

    public function test_readlist_is_paginated_after_twenty_four_titles(): void
    {
        $user = User::factory()->create();
        ReadingList::factory()
            ->count(25)
            ->for($user)
            ->state(['status' => 'want_to_read'])
            ->create();

        $response = $this->get(route('profiles.readlist', $user));

        $response->assertOk();
        $this->assertCount(24, $response->inertiaProps('readlist.data'));
    }

    public function test_owner_sees_quick_add_suggestions_without_already_tracked_literature(): void
    {
        $user = User::factory()->create();
        $available = Literature::factory()->create(['title' => 'Available Next Read']);
        $alreadyTracked = Literature::factory()->create(['title' => 'Already Reading']);
        ReadingList::factory()->for($user)->for($alreadyTracked)->create(['status' => 'reading']);

        $response = $this->actingAs($user)
            ->get(route('profiles.readlist', $user))
            ->assertOk();

        $this->assertTrue($response->inertiaProps('isOwner'));
        $this->assertContains($available->id, collect($response->inertiaProps('suggestions'))->pluck('id'));
        $this->assertNotContains($alreadyTracked->id, collect($response->inertiaProps('suggestions'))->pluck('id'));
    }

    public function test_quick_add_saves_literature_and_returns_to_the_readlist(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create();

        $this->actingAs($user)
            ->put(route('reading-list.update', $literature), [
                'status' => 'want_to_read',
                'return_to' => 'readlist',
            ])
            ->assertRedirect(route('profiles.readlist', $user));

        $this->assertDatabaseHas('reading_lists', [
            'user_id' => $user->id,
            'literature_id' => $literature->id,
            'status' => 'want_to_read',
        ]);
    }

    public function test_public_reader_does_not_see_another_users_quick_add_form(): void
    {
        $user = User::factory()->create();

        $response = $this->get(route('profiles.readlist', $user))->assertOk();

        $this->assertFalse($response->inertiaProps('isOwner'));
        $this->assertSame([], $response->inertiaProps('suggestions'));
    }
}
