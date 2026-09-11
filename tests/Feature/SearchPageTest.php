<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\AuthorAlias;
use App\Models\Literature;
use App\Models\ReadingList;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SearchPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_global_search_combines_literature_related_authors_and_matching_readers(): void
    {
        $literature = Literature::factory()->create([
            'title' => 'Haikyu!!',
            'original_title' => 'Haikyuu!!',
            'synopsis' => 'A volleyball team works its way toward the national tournament.',
            'language' => 'en',
        ]);
        $creator = Author::factory()->create(['name' => 'Haruichi Furudate']);
        $namedAuthor = Author::factory()->create(['name' => 'Haikyu Researcher']);
        $literature->authors()->attach($creator, ['role' => 'author', 'position' => 0]);

        $reader = User::factory()->create([
            'name' => 'Volleyball Reader',
            'username' => 'haikyuu_reader',
        ]);
        ReadingList::factory()->for($reader)->for(Literature::factory())->create(['status' => 'completed']);
        Review::factory()->for($reader)->for(Literature::factory())->create();

        $deactivated = User::factory()->create([
            'name' => 'Hidden Haikyu Reader',
            'username' => 'hidden_haikyu',
            'deactivated_at' => now(),
        ]);

        $response = $this->get(route('search.index', ['q' => 'Haikyu']));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Search/Index')
            ->where('query', 'Haikyu')
            ->where('scope', 'all')
            ->where('literatures.0.title', 'Haikyuu!!')
            ->where('readers.0.username', 'haikyuu_reader')
            ->where('readers.0.completed_count', 1)
            ->where('readers.0.reviews_count', 1));
        $this->assertEqualsCanonicalizing(
            ['Haikyu Researcher', 'Haruichi Furudate'],
            collect($response->inertiaProps('authors'))->pluck('name')->all(),
        );
        $this->assertNotContains($deactivated->name, collect($response->inertiaProps('readers'))->pluck('name')->all());
    }

    public function test_global_header_search_submits_to_the_combined_search_page(): void
    {
        $response = $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-react-header-props', false)
            ->assertSee('data-react-header', false);

        $props = $this->embeddedReactProps($response->getContent(), 'data-react-header-props');

        $this->assertSame(route('search.index'), $props['routes']['search']);
    }

    public function test_global_search_displays_a_mangas_global_title_while_native_title_remains_searchable(): void
    {
        Literature::factory()->create([
            'type' => 'manga',
            'title' => 'Haikyu!!',
            'original_title' => 'ハイキュー!!',
        ]);

        $response = $this->get(route('search.index', ['q' => 'ハイキュー']));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Search/Index')
            ->where('literatures.0.title', 'Haikyu!!'));
    }

    public function test_global_search_finds_an_author_by_a_known_alias(): void
    {
        $author = Author::factory()->create(['name' => 'J. K. Rowling']);
        $literature = Literature::factory()->create(['title' => 'The Cuckoo’s Calling']);
        $literature->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        AuthorAlias::factory()->for($author)->create([
            'name' => 'Robert Galbraith',
            'normalized_name' => 'robert galbraith',
            'source' => 'google-books',
            'external_id' => null,
        ]);

        $this->get(route('search.index', ['q' => 'Robert Galbraith']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('literatures.0.title', 'The Cuckoo’s Calling')
                ->where('authors.0.name', 'J. K. Rowling'));
    }

    public function test_authenticated_header_shows_the_account_menu_and_feature_links(): void
    {
        $user = User::factory()->create([
            'name' => 'Axel Reader',
            'username' => 'axellgab',
        ]);

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('data-react-header-props', false)
            ->assertSeeText('axellgab')
            ->assertSeeInOrder(['Profile', 'Activity', 'Literature', 'Reviews', 'Readlist', 'Connections'])
            ->assertSee('"logout_url":"', false);
    }

    public function test_search_does_not_show_a_non_english_synopsis_as_english_copy(): void
    {
        Literature::factory()->create([
            'title' => 'Bahasa Story',
            'language' => 'id',
            'synopsis' => 'Sinopsis ini ditulis dalam bahasa Indonesia.',
        ]);

        $this->get(route('search.index', ['q' => 'Bahasa Story']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('literatures.0.synopsis', 'English synopsis unavailable.'));
    }

    public function test_search_category_only_displays_the_selected_result_type(): void
    {
        $literature = Literature::factory()->create(['title' => 'Naruto']);
        $author = Author::factory()->create(['name' => 'Naruto Researcher']);
        $literature->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        User::factory()->create(['name' => 'Naruto Reader', 'username' => 'naruto-reader']);

        $this->get(route('search.index', ['q' => 'Naruto', 'scope' => 'authors']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('scope', 'authors')
                ->has('authors', 1));

        $this->get(route('search.index', ['q' => 'Naruto', 'scope' => 'literature']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('scope', 'literature')
                ->has('literatures', 1));
    }

    /** @return array<string, mixed> */
    private function embeddedReactProps(string $content, string $attribute): array
    {
        $matched = preg_match('/<script[^>]*'.preg_quote($attribute, '/').'[^>]*>(.*?)<\/script>/s', $content, $matches);

        $this->assertSame(1, $matched);

        return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    }
}
