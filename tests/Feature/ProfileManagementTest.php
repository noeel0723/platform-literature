<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Discussion;
use App\Models\Literature;
use App\Models\ReadingList;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
        Review::factory()->for($user)->for(Literature::factory())->create();
        Discussion::factory()->for($user)->for(Literature::factory())->create();

        $this->get(route('profiles.show', $user))
            ->assertOk()
            ->assertSeeText('Imanuel Reader')
            ->assertSeeText('@imanuel_reader')
            ->assertSeeText('Makassar, Indonesia')
            ->assertSeeText('I read fantasy novels and graphic narratives.')
            ->assertSeeInOrder(['First Favorite', 'Second Favorite'])
            ->assertSeeInOrder(['First Author', 'Second Author'])
            ->assertDontSeeText('Edit profile');
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
