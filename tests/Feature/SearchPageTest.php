<?php

namespace Tests\Feature;

use App\Models\Author;
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
            ->assertSee('action="'.route('search.index').'"', false)
            ->assertSeeText('Search literature, authors, or readers');
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
}
