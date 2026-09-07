<?php

namespace Tests\Feature;

use App\Models\Literature;
use App\Models\ReadingList;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

        $this->get(route('profiles.readlist', $user))
            ->assertOk()
            ->assertSeeText("Readlist Owner's Readlist")
            ->assertSeeText('Saved for Later')
            ->assertDontSeeText('Currently Reading')
            ->assertDontSeeText('Already Completed')
            ->assertSee('data-readlist-item', false);
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

        $response
            ->assertSee('data-profile-readlist-preview', false)
            ->assertSee('href="'.route('profiles.readlist', $user).'"', false)
            ->assertSeeText('View full Readlist')
            ->assertSee('title="Saved Title 1"', false)
            ->assertSee('title="Saved Title 4"', false)
            ->assertDontSee('title="Saved Title 5"', false);
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
        $this->assertSame(24, substr_count($response->getContent(), 'data-readlist-item'));
    }
}
