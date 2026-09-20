<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\CanonicalWork;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProfileFavoriteSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_cannot_search_profile_favorites(): void
    {
        $this->getJson(route('profiles.favorites.literatures', ['q' => 'Naruto']))
            ->assertUnauthorized();
        $this->getJson(route('profiles.favorites.authors', ['q' => 'Stephen']))
            ->assertUnauthorized();
    }

    public function test_literature_search_is_canonical_aware_and_matches_title_and_author_without_external_requests(): void
    {
        Http::preventStrayRequests();

        $user = User::factory()->create();
        $author = Author::factory()->create(['name' => 'Masashi Kishimoto']);
        $preferred = Literature::factory()->create([
            'title' => 'Naruto',
            'original_title' => null,
            'publication_year' => 1999,
            'type' => 'manga',
        ]);
        $alternate = Literature::factory()->create([
            'title' => 'Naruto Collector Edition',
            'original_title' => 'NARUTO -ナルト-',
            'type' => 'manga',
        ]);
        $alternate->authors()->attach($author, ['role' => 'author', 'position' => 1]);
        $this->canonicalize($preferred, $alternate);

        $this->actingAs($user)
            ->getJson(route('profiles.favorites.literatures', ['q' => 'Collector Edition']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $preferred->id)
            ->assertJsonPath('results.0.title', 'Naruto');

        $this->actingAs($user)
            ->getJson(route('profiles.favorites.literatures', ['q' => 'NARUTO -ナルト-']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $preferred->id);

        $this->actingAs($user)
            ->getJson(route('profiles.favorites.literatures', ['q' => 'Masashi Kishimoto']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $preferred->id);
    }

    public function test_literature_search_limits_results_to_twenty(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 25) as $position) {
            Literature::factory()->create(['title' => "Searchable Literature {$position}"]);
        }

        $this->actingAs($user)
            ->getJson(route('profiles.favorites.literatures', ['q' => 'Searchable Literature']))
            ->assertOk()
            ->assertJsonCount(20, 'results');
    }

    public function test_author_search_matches_local_names_and_limits_results_to_twenty(): void
    {
        Http::preventStrayRequests();

        $user = User::factory()->create();
        foreach (range(1, 25) as $position) {
            Author::factory()->create(['name' => "Stephen Writer {$position}"]);
        }
        Author::factory()->create(['name' => 'Unrelated Author']);

        $response = $this->actingAs($user)
            ->getJson(route('profiles.favorites.authors', ['q' => 'Stephen Writer']))
            ->assertOk()
            ->assertJsonCount(20, 'results');

        $this->assertTrue(
            collect($response->json('results'))->every(fn (array $author): bool => str_contains($author['name'], 'Stephen Writer')),
        );
    }

    private function canonicalize(Literature $preferred, Literature $alternate): CanonicalWork
    {
        $work = CanonicalWork::factory()->create([
            'preferred_literature_id' => $preferred->id,
            'canonical_title' => $preferred->title,
            'normalized_title' => str($preferred->title)->lower()->toString(),
            'type' => $preferred->type,
            'publication_year' => $preferred->publication_year,
        ]);

        foreach ([$preferred, $alternate] as $literature) {
            LiteratureSourceMapping::factory()
                ->for($work)
                ->for($literature)
                ->for($literature->apiSource)
                ->create(['source_external_id' => 'favorite-search-'.$literature->id]);
        }

        return $work;
    }
}
