<?php

namespace Tests\Feature;

use App\Models\Literature;
use App\Models\ReadingList;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Literature')
            ->where('profile.name', 'Shelf Reader')
            ->where('navigation.current', 'literature')
            ->where('completedLiterature.total', 1)
            ->where('completedLiterature.data.0.literature.title', 'Completed Story')
            ->where('completedLiterature.data.0.literature.year', 2024)
            ->where('completedLiterature.data.0.rating', 4.5)
        );
        $this->assertNotContains('Saved For Later', collect($response->inertiaProps('completedLiterature.data'))->pluck('literature.title'));
        $this->assertNotContains('Currently Reading', collect($response->inertiaProps('completedLiterature.data'))->pluck('literature.title'));
    }

    public function test_profile_navigation_places_literature_after_activity_for_the_owner(): void
    {
        $user = User::factory()->create(['username' => 'navigation_reader']);

        $response = $this->actingAs($user)
            ->get(route('profiles.literature', $user))
            ->assertOk();

        $this->assertSame(
            ['Profile', 'Activity', 'Literature', 'Diary', 'Reviews', 'Readlist'],
            collect($response->inertiaProps('navigation.links'))->pluck('label')->all(),
        );
    }
}
