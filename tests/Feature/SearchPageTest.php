<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\AuthorAlias;
use App\Models\Literature;
use App\Models\ReadingList;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

        $response
            ->assertOk()
            ->assertSeeText('Search results for')
            ->assertSeeText('Haikyuu!!')
            ->assertSeeText('Haruichi Furudate')
            ->assertSeeText($namedAuthor->name)
            ->assertSeeText('@haikyuu_reader')
            ->assertSeeText('1 completed')
            ->assertSeeText('1 reviews')
            ->assertDontSeeText($deactivated->name)
            ->assertSee('data-search-literature', false)
            ->assertSee('data-search-author', false)
            ->assertSee('data-search-member', false);
    }

    public function test_global_header_search_submits_to_the_combined_search_page(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-react-header-props', false)
            ->assertSee('data-react-header', false)
            ->assertSee('&quot;search&quot;:&quot;', false);
    }

    public function test_global_search_displays_a_mangas_global_title_while_native_title_remains_searchable(): void
    {
        Literature::factory()->create([
            'type' => 'manga',
            'title' => 'Haikyu!!',
            'original_title' => 'ハイキュー!!',
        ]);

        $response = $this->get(route('search.index', ['q' => 'ハイキュー']));

        $response
            ->assertOk()
            ->assertSeeText('Haikyu!!')
            ->assertSee('data-search-literature', false);
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
            ->assertOk()
            ->assertSeeText('J. K. Rowling')
            ->assertSeeText('The Cuckoo’s Calling')
            ->assertSee('data-search-literature', false)
            ->assertSee('data-search-author', false);
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
            ->assertSeeInOrder(['Profile', 'Activity', 'Literature', 'Reviews', 'Readlist'])
            ->assertSee('&quot;logout_url&quot;:&quot;', false);
    }

    public function test_search_does_not_show_a_non_english_synopsis_as_english_copy(): void
    {
        Literature::factory()->create([
            'title' => 'Bahasa Story',
            'language' => 'id',
            'synopsis' => 'Sinopsis ini ditulis dalam bahasa Indonesia.',
        ]);

        $this->get(route('search.index', ['q' => 'Bahasa Story']))
            ->assertOk()
            ->assertSeeText('English synopsis unavailable.')
            ->assertDontSeeText('Sinopsis ini ditulis dalam bahasa Indonesia.');
    }

    public function test_search_category_only_displays_the_selected_result_type(): void
    {
        $literature = Literature::factory()->create(['title' => 'Naruto']);
        $author = Author::factory()->create(['name' => 'Naruto Researcher']);
        $literature->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        User::factory()->create(['name' => 'Naruto Reader', 'username' => 'naruto-reader']);

        $this->get(route('search.index', ['q' => 'Naruto', 'scope' => 'authors']))
            ->assertOk()
            ->assertSee('aria-current="page"', false)
            ->assertSee('data-search-author', false)
            ->assertDontSee('data-search-literature', false)
            ->assertDontSee('data-search-member', false);

        $this->get(route('search.index', ['q' => 'Naruto', 'scope' => 'literature']))
            ->assertOk()
            ->assertSee('data-search-literature', false)
            ->assertDontSee('data-search-author', false)
            ->assertDontSee('data-search-member', false);
    }
}
