<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Literature;
use App\Models\ReadingList;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_profile_shows_identity_statistics_and_ordered_favorites(): void
    {
        $user = User::factory()->create([
            'name' => 'Imanuel Reader',
            'username' => 'imanuel_reader',
            'location' => 'Makassar, Indonesia',
            'bio' => 'I read fantasy novels and graphic narratives.',
        ]);
        $firstLiterature = Literature::factory()->create(['title' => 'First Favorite']);
        $secondLiterature = Literature::factory()->create(['title' => 'Second Favorite']);
        $firstAuthor = Author::factory()->create(['name' => 'First Author']);
        $secondAuthor = Author::factory()->create(['name' => 'Second Author']);

        $user->favoriteLiteratures()->attach([
            $secondLiterature->id => ['position' => 2],
            $firstLiterature->id => ['position' => 1],
        ]);
        $user->favoriteAuthors()->attach([
            $secondAuthor->id => ['position' => 2],
            $firstAuthor->id => ['position' => 1],
        ]);
        ReadingList::factory()->for($user)->for(Literature::factory())->create(['status' => 'completed']);
        ReadingList::factory()->for($user)->for(Literature::factory())->create(['status' => 'want_to_read']);
        Review::factory()->for($user)->for(Literature::factory())->create(['rating' => 4.5]);

        $response = $this->get(route('profiles.show', $user))->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')
            ->where('profile.name', 'Imanuel Reader')
            ->where('profile.username', 'imanuel_reader')
            ->where('profile.location', 'Makassar, Indonesia')
            ->where('profile.bio', 'I read fantasy novels and graphic narratives.')
            ->where('profile.is_owner', false)
            ->where('profile.stats.literature', 2)
            ->where('profile.stats.completed', 1)
            ->where('profile.stats.reviews', 1)
            ->where('routes.diary', null)
            ->where('navigation.current', 'profile')
            ->has('favoriteLiteratures', 2)
            ->has('favoriteAuthors', 2)
            ->has('ratingDistribution', 10)
            ->where('ratingDistribution.8.rating', '4.5')
            ->where('ratingDistribution.8.count', 1)
        );
        $this->assertSame(['First Favorite', 'Second Favorite'], collect($response->inertiaProps('favoriteLiteratures'))->pluck('title')->all());
        $this->assertSame(['First Author', 'Second Author'], collect($response->inertiaProps('favoriteAuthors'))->pluck('name')->all());
        $this->assertSame(['Profile', 'Stats', 'Literature', 'Reviews', 'Readlist', 'Lists', 'Connections'], collect($response->inertiaProps('navigation.links'))->pluck('label')->all());
    }

    public function test_owner_profile_is_the_only_navigation_entry_point_to_the_diary(): void
    {
        $user = User::factory()->create([
            'name' => 'Diary Reader',
            'username' => 'diary_reader',
            'bio' => null,
        ]);
        $literature = Literature::factory()->create();

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('diary.index'), false);

        $this->actingAs($user)->get(route('literatures.show', $literature))
            ->assertOk()
            ->assertDontSeeText('Manage your reading')
            ->assertDontSee('href="'.route('diary.index').'"', false);

        $response = $this->actingAs($user)->get(route('profiles.show', $user))->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Show')
            ->where('profile.name', 'Diary Reader')
            ->where('profile.username', 'diary_reader')
            ->where('profile.bio', null)
            ->where('profile.is_owner', true)
            ->where('routes.edit', route('profiles.edit'))
            ->where('routes.diary', route('diary.index'))
            ->where('routes.activity', route('activity.index'))
        );
        $this->assertSame(['Profile', 'Stats', 'Activity', 'Literature', 'Diary', 'Reviews', 'Readlist', 'Lists', 'Connections'], collect($response->inertiaProps('navigation.links'))->pluck('label')->all());
    }

    public function test_owner_profile_pages_use_one_consistent_shared_sub_navigation(): void
    {
        $user = User::factory()->create(['username' => 'consistent_navigation']);

        $routes = [
            route('profiles.show', $user),
            route('activity.index'),
            route('profiles.literature', $user),
            route('diary.index'),
            route('profiles.reviews', $user),
            route('profiles.readlist', $user),
            route('profiles.connections', $user),
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($user)->get($route)->assertOk();

            $this->assertSame(['Profile', 'Stats', 'Activity', 'Literature', 'Diary', 'Reviews', 'Readlist', 'Lists', 'Connections'], collect($response->inertiaProps('navigation.links'))->pluck('label')->all());
            $this->assertContains($response->inertiaProps('navigation.current'), ['profile', 'activity', 'literature', 'diary', 'reviews', 'readlist', 'connections']);
        }
    }

    public function test_mutual_friend_profile_exposes_activity_and_diary_navigation(): void
    {
        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $viewer->following()->attach($friend);
        $friend->following()->attach($viewer);

        $response = $this->actingAs($viewer)->get(route('profiles.show', $friend))->assertOk();

        $this->assertTrue($response->inertiaProps('profile.is_friend'));
        $this->assertSame(route('profiles.activity', $friend), $response->inertiaProps('routes.activity'));
        $this->assertSame(route('profiles.diary', $friend), $response->inertiaProps('routes.diary'));
        $this->assertSame(
            ['Profile', 'Stats', 'Activity', 'Literature', 'Diary', 'Reviews', 'Readlist', 'Lists', 'Connections'],
            collect($response->inertiaProps('navigation.links'))->pluck('label')->all(),
        );
    }

    public function test_owner_can_update_identity_and_four_favorites(): void
    {
        $user = User::factory()->create(['username' => 'old_reader']);
        $literatures = Literature::factory()->count(4)->create();
        $authors = Author::factory()->count(4)->create();

        $this->actingAs($user)->put(route('profiles.update'), [
            'name' => 'Updated Reader',
            'username' => 'updated_reader',
            'location' => 'Manado, Indonesia',
            'bio' => 'Exploring literature across formats.',
            'favorite_literature_ids' => $literatures->pluck('id')->reverse()->values()->all(),
            'favorite_author_ids' => $authors->pluck('id')->all(),
        ])->assertRedirect(route('profiles.show', 'updated_reader'));

        $user->refresh();

        $this->assertSame('Updated Reader', $user->name);
        $this->assertSame('updated_reader', $user->username);
        $this->assertSame('Manado, Indonesia', $user->location);
        $this->assertSame('Exploring literature across formats.', $user->bio);
        $this->assertSame(
            $literatures->pluck('id')->reverse()->values()->all(),
            $user->favoriteLiteratures()->pluck('literatures.id')->all(),
        );
        $this->assertSame($authors->pluck('id')->all(), $user->favoriteAuthors()->pluck('authors.id')->all());
    }

    public function test_profile_editor_is_react_and_exposes_four_visual_favorite_slots(): void
    {
        $user = User::factory()->create();
        $literature = Literature::factory()->create(['title' => 'Visual Favorite', 'cover_url' => 'https://example.test/cover.jpg']);
        $author = Author::factory()->create(['name' => 'Portrait Author', 'image_url' => 'https://example.test/author.jpg']);
        $user->favoriteLiteratures()->attach($literature, ['position' => 1]);
        $user->favoriteAuthors()->attach($author, ['position' => 1]);

        $this->actingAs($user)->get(route('profiles.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                ->where('profile.username', $user->username)
                ->where('literatures.0.cover_url', 'https://example.test/cover.jpg')
                ->where('authors.0.image_url', 'https://example.test/author.jpg')
                ->has('favoriteLiteratureIds', 4)
                ->has('favoriteAuthorIds', 4)
                ->where('routes.update', route('profiles.update')));
    }

    public function test_react_profile_form_can_submit_with_http_method_spoofing(): void
    {
        $user = User::factory()->create(['username' => 'before_edit']);

        $this->actingAs($user)->post(route('profiles.update'), [
            '_method' => 'put',
            'name' => 'After Edit',
            'username' => 'after_edit',
            'favorite_literature_ids' => [],
            'favorite_author_ids' => [],
        ])->assertRedirect(route('profiles.show', 'after_edit'));

        $this->assertSame('After Edit', $user->fresh()->name);
    }

    public function test_profile_rejects_more_than_four_favorites_and_duplicate_username(): void
    {
        $user = User::factory()->create(['username' => 'current_reader']);
        User::factory()->create(['username' => 'taken_reader']);
        $literatures = Literature::factory()->count(5)->create();

        $this->actingAs($user)
            ->from(route('profiles.edit'))
            ->put(route('profiles.update'), [
                'name' => $user->name,
                'username' => 'taken_reader',
                'favorite_literature_ids' => $literatures->pluck('id')->all(),
                'favorite_author_ids' => [],
            ])
            ->assertRedirect(route('profiles.edit'))
            ->assertSessionHasErrors(['username', 'favorite_literature_ids']);

        $this->assertSame('current_reader', $user->fresh()->username);
    }

    public function test_guest_cannot_edit_a_profile(): void
    {
        $this->get(route('profiles.edit'))->assertRedirect(route('login'));
        $this->put(route('profiles.update'), [])->assertRedirect(route('login'));
    }

    public function test_profile_is_resolved_by_username(): void
    {
        $user = User::factory()->create(['username' => 'public_reader']);

        $this->get('/members/public_reader')
            ->assertOk()
            ->assertSeeText($user->name);

        $this->get('/members/missing_reader')->assertNotFound();
    }
}
