<?php

namespace Tests\Feature;

use App\Models\CanonicalWork;
use App\Models\Category;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Models\ReadingList;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
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
        $anotherCompleted = Literature::factory()->create(['title' => 'Another Completed Story']);
        $saved = Literature::factory()->create(['title' => 'Saved For Later']);
        $inProgress = Literature::factory()->create(['title' => 'Currently Reading']);

        ReadingList::factory()->for($user)->for($completed)->create([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        ReadingList::factory()->for($user)->for($anotherCompleted)->create([
            'status' => 'completed',
            'completed_at' => now()->subDay(),
        ]);
        ReadingList::factory()->for($user)->for($saved)->create(['status' => 'want_to_read']);
        ReadingList::factory()->for($user)->for($inProgress)->create(['status' => 'reading']);
        Review::factory()->for($user)->for($completed)->create(['rating' => 4.5]);

        $response = $this->get(route('profiles.literature', $user));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Literature')
            ->where('profile.name', 'Shelf Reader')
            ->where('navigation.current', 'literature')
            ->where('completedLiterature.total', 2)
            ->where('completedLiterature.data.0.literature.title', 'Completed Story')
            ->where('completedLiterature.data.0.literature.year', 2024)
            ->where('completedLiterature.data.0.rating', 4.5)
        );
        $this->assertEqualsCanonicalizing(
            ['Completed Story', 'Another Completed Story'],
            collect($response->inertiaProps('completedLiterature.data'))->pluck('literature.title')->all(),
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
            ['Profile', 'Stats', 'Activity', 'Literature', 'Diary', 'Reviews', 'Readlist', 'Lists', 'Connections'],
            collect($response->inertiaProps('navigation.links'))->pluck('label')->all(),
        );
    }

    public function test_genre_filter_only_shows_completed_literature_in_that_genre(): void
    {
        $user = User::factory()->create(['username' => 'genre_reader']);
        $horror = Category::factory()->create(['name' => 'Horror', 'slug' => 'horror']);
        $fantasy = Category::factory()->create(['name' => 'Fantasy', 'slug' => 'fantasy']);
        $horrorLiterature = Literature::factory()->create(['title' => 'Haunted House']);
        $fantasyLiterature = Literature::factory()->create(['title' => 'Enchanted Forest']);
        $horrorLiterature->categories()->attach($horror);
        $fantasyLiterature->categories()->attach($fantasy);

        ReadingList::factory()->for($user)->for($horrorLiterature)->create([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        ReadingList::factory()->for($user)->for($fantasyLiterature)->create([
            'status' => 'completed',
            'completed_at' => now()->subDay(),
        ]);

        $response = $this->get(route('profiles.literature', [
            'user' => $user,
            'genre' => 'horror',
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Literature')
            ->where('activeGenre.name', 'Horror')
            ->where('activeGenre.slug', 'horror')
            ->where('completedLiterature.total', 1)
            ->where('completedLiterature.data.0.literature.title', 'Haunted House'));
        $this->assertNotContains(
            'Enchanted Forest',
            collect($response->inertiaProps('completedLiterature.data'))->pluck('literature.title'),
        );
    }

    public function test_genre_filter_uses_categories_from_a_canonical_sibling(): void
    {
        $user = User::factory()->create(['username' => 'canonical_reader']);
        $horror = Category::factory()->create(['name' => 'Horror', 'slug' => 'horror']);
        $preferred = Literature::factory()->create(['title' => 'Canonical Horror']);
        $alternate = Literature::factory()->create(['title' => 'Canonical Horror Alternate']);
        $alternate->categories()->attach($horror);
        $work = CanonicalWork::factory()->create([
            'preferred_literature_id' => $preferred->id,
            'canonical_title' => 'Canonical Horror',
            'normalized_title' => 'canonical horror',
            'type' => $preferred->type,
        ]);
        $this->mapToCanonicalWork($work, $preferred, 'preferred');
        $this->mapToCanonicalWork($work, $alternate, 'alternate');
        ReadingList::factory()->for($user)->for($preferred)->create([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $response = $this->get(route('profiles.literature', [
            'user' => $user,
            'genre' => 'horror',
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('completedLiterature.total', 1)
            ->where('completedLiterature.data.0.literature.title', 'Canonical Horror'));
    }

    public function test_genre_filter_is_preserved_in_pagination_links(): void
    {
        $user = User::factory()->create(['username' => 'paged_reader']);
        $horror = Category::factory()->create(['name' => 'Horror', 'slug' => 'horror']);

        Literature::factory()
            ->count(49)
            ->create()
            ->each(function (Literature $literature) use ($horror, $user): void {
                $literature->categories()->attach($horror);
                ReadingList::factory()->for($user)->for($literature)->create([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            });

        $response = $this->get(route('profiles.literature', [
            'user' => $user,
            'genre' => 'horror',
        ]));

        $this->assertSame(48, count($response->inertiaProps('completedLiterature.data')));
        $this->assertStringContainsString(
            'genre=horror',
            $response->inertiaProps('completedLiterature.next_page_url'),
        );
    }

    public function test_literature_detail_genre_link_targets_authenticated_viewers_literature_page(): void
    {
        $viewer = User::factory()->create(['username' => 'axel']);
        $horror = Category::factory()->create(['name' => 'Horror', 'slug' => 'horror']);
        $literature = Literature::factory()->create(['title' => 'Linked Horror']);
        $alternate = Literature::factory()->create(['title' => 'Linked Horror Alternate']);
        $alternate->categories()->attach($horror);
        $work = CanonicalWork::factory()->create([
            'preferred_literature_id' => $literature->id,
            'canonical_title' => 'Linked Horror',
            'normalized_title' => 'linked horror',
            'type' => $literature->type,
        ]);
        $this->mapToCanonicalWork($work, $literature, 'linked-preferred');
        $this->mapToCanonicalWork($work, $alternate, 'linked-alternate');

        $response = $this->actingAs($viewer)->get(route('literatures.show', $literature));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Show')
            ->where('literature.genre_links.0.name', 'Horror')
            ->where('literature.genre_links.0.slug', 'horror')
            ->where('literature.genre_links.0.url', route('profiles.literature', [
                'user' => $viewer,
                'genre' => 'horror',
            ])));
    }

    public function test_literature_detail_genre_is_not_clickable_for_guests(): void
    {
        $horror = Category::factory()->create(['name' => 'Horror', 'slug' => 'horror']);
        $literature = Literature::factory()->create(['title' => 'Guest Horror']);
        $literature->categories()->attach($horror);

        $this->get(route('literatures.show', $literature))
            ->assertInertia(fn (Assert $page) => $page
                ->where('literature.genre_links.0.url', null));
    }

    public function test_standalone_genre_catalog_route_no_longer_exists(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('literatures.genre'));
        $this->get('/catalog/genre/horror')->assertNotFound();
    }

    private function mapToCanonicalWork(
        CanonicalWork $work,
        Literature $literature,
        string $externalId,
    ): void {
        LiteratureSourceMapping::factory()
            ->for($work)
            ->for($literature)
            ->for($literature->apiSource)
            ->create(['source_external_id' => $externalId]);
    }
}
