<?php

namespace Tests\Feature;

use App\Models\ApiSource;
use App\Models\Author;
use App\Models\Category;
use App\Models\Literature;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LiteratureCatalogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_catalog_home_renders_increment_one_interface(): void
    {
        $this->createLiterature(
            [
                'title' => 'Bumi Manusia',
                'slug' => 'bumi-manusia',
                'type' => 'book',
            ],
            'Google Books',
            ['Pramoedya Ananta Toer'],
            ['Fiksi sejarah'],
        );

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSeeText('Satu rak untuk setiap cerita')
            ->assertSeeText('Bumi Manusia')
            ->assertSeeText('Google Books');
    }

    public function test_catalog_can_be_filtered_by_query_and_type(): void
    {
        $this->createLiterature(
            ['title' => 'Bumi Manusia', 'slug' => 'bumi-manusia', 'type' => 'book'],
            'Google Books',
            ['Pramoedya Ananta Toer'],
        );
        $this->createLiterature(
            ['title' => 'Fullmetal Alchemist', 'slug' => 'fullmetal-alchemist', 'type' => 'manga'],
            'AniList',
            ['Hiromu Arakawa'],
        );

        $response = $this->get(route('literatures.index', [
            'q' => 'bumi',
            'type' => 'book',
        ]));

        $response
            ->assertOk()
            ->assertSeeText('Bumi Manusia')
            ->assertDontSeeText('Fullmetal Alchemist');
    }

    #[TestWith(['Alan Moore'])]
    #[TestWith(['Misteri'])]
    public function test_catalog_can_be_searched_by_author_or_category(string $query): void
    {
        $this->createLiterature(
            ['title' => 'Watchmen', 'slug' => 'watchmen', 'type' => 'western-comic'],
            'Comic Vine',
            ['Alan Moore'],
            ['Misteri'],
        );

        $this->get(route('literatures.index', ['q' => $query]))
            ->assertOk()
            ->assertSeeText('Watchmen');
    }

    public function test_literature_detail_renders_catalog_metadata(): void
    {
        $this->createLiterature(
            ['title' => 'Watchmen', 'slug' => 'watchmen', 'type' => 'western-comic'],
            'Comic Vine',
            ['Alan Moore', 'Dave Gibbons'],
            ['Misteri'],
        );

        $response = $this->get(route('literatures.show', 'watchmen'));

        $response
            ->assertOk()
            ->assertSeeText('Watchmen')
            ->assertSeeText('Alan Moore')
            ->assertSeeText('Comic Vine');
    }

    public function test_unknown_literature_returns_not_found(): void
    {
        $this->get(route('literatures.show', 'tidak-tersedia'))
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $authors
     * @param  array<int, string>  $categories
     */
    private function createLiterature(
        array $attributes,
        string $sourceName,
        array $authors,
        array $categories = [],
    ): Literature {
        $source = ApiSource::factory()->create([
            'key' => Str::slug($sourceName),
            'name' => $sourceName,
        ]);
        $literature = Literature::factory()->for($source)->create($attributes);

        foreach ($authors as $position => $name) {
            $author = Author::factory()->create([
                'name' => $name,
                'slug' => Str::slug($name),
            ]);

            $literature->authors()->attach($author, [
                'role' => 'author',
                'position' => $position,
            ]);
        }

        foreach ($categories as $name) {
            $category = Category::factory()->create([
                'name' => $name,
                'slug' => Str::slug($name),
            ]);

            $literature->categories()->attach($category);
        }

        return $literature;
    }
}
