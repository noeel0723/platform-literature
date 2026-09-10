<?php

namespace Tests\Feature;

use App\Models\Literature;
use App\Models\ReadingList;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProfileLiteraturePageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_profile_literature_page_only_shows_completed_titles(): void
    {
        $user = User::factory()->create([
            'name' => 'Shelf Reader',
            'username' => 'shelf_reader',
        ]);
        $completed = Literature::factory()->create([
            'title' => 'Completed Story',
            'publication_year' => 2024,
        ]);
        $saved = Literature::factory()->create(['title' => 'Saved For Later']);
        $inProgress = Literature::factory()->create(['title' => 'Currently Reading']);

        ReadingList::factory()->for($user)->for($completed)->create([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        ReadingList::factory()->for($user)->for($saved)->create(['status' => 'want_to_read']);
        ReadingList::factory()->for($user)->for($inProgress)->create(['status' => 'reading']);
        Review::factory()->for($user)->for($completed)->create(['rating' => 4.5]);

        $response = $this->get(route('profiles.literature', $user));

        $response
            ->assertOk()
            ->assertSeeText("Shelf Reader's Literature")
            ->assertSeeText('Completed Story')
            ->assertDontSeeText('Saved For Later')
            ->assertDontSeeText('Currently Reading')
            ->assertSee('data-profile-literature-grid', false)
            ->assertSee('data-density="compact"', false)
            ->assertSee('data-profile-literature-item', false)
            ->assertSee('"current":"literature"', false)
            ->assertSee('4.5 out of 5 stars', false);

        $this->assertSame(1, substr_count($response->getContent(), 'data-profile-literature-item'));
    }

    public function test_profile_navigation_places_literature_after_activity_for_the_owner(): void
    {
        $user = User::factory()->create(['username' => 'navigation_reader']);

        $this->actingAs($user)
            ->get(route('profiles.literature', $user))
            ->assertOk()
            ->assertSeeInOrder(['Activity', 'Literature', 'Diary'])
            ->assertDontSee('>Favorites</a>', false)
            ->assertDontSee('>Completed</a>', false);
    }
}
