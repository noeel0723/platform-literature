<?php

namespace Tests\Feature;

use App\Models\ApiSource;
use App\Models\Author;
use App\Models\Literature;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthorDetailTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_author_page_uses_slug_and_lists_only_linked_works(): void
    {
        config()->set('services.knowledge_graph.key', null);
        $source = ApiSource::factory()->create(['name' => 'AniList']);
        $author = Author::factory()->create([
            'name' => 'Haruichi Furudate',
            'slug' => 'haruichi-furudate',
            'biography' => 'Japanese manga artist known for Haikyu!!',
            'image_url' => 'https://images.example.test/furudate.jpg',
        ]);
        $haikyu = Literature::factory()->for($source)->create([
            'title' => 'Haikyu!!',
            'slug' => 'haikyu',
            'type' => 'manga',
            'publication_year' => 2012,
        ]);
        $unrelated = Literature::factory()->for($source)->create([
            'title' => 'Unrelated Work',
            'slug' => 'unrelated-work',
        ]);
        $author->literatures()->attach($haikyu, ['role' => 'author', 'position' => 0]);

        $this->get(route('authors.show', $author))
            ->assertOk()
            ->assertSeeText('Works by')
            ->assertSeeText('Haruichi Furudate')
            ->assertSeeText('Japanese manga artist known for Haikyu!!')
            ->assertSee('https://images.example.test/furudate.jpg', false)
            ->assertSee(route('literatures.show', $haikyu), false)
            ->assertDontSeeText($unrelated->title);
    }

    public function test_missing_author_metadata_is_enriched_from_knowledge_graph(): void
    {
        Cache::flush();
        $this->configureKnowledgeGraph();
        Http::preventStrayRequests();
        Http::fake([
            'https://kgsearch.googleapis.com/v1/entities:search*' => Http::response([
                'itemListElement' => [[
                    'resultScore' => 990,
                    'result' => [
                        '@id' => 'kg:/m/author',
                        '@type' => ['Thing', 'Person'],
                        'name' => 'Haruichi Furudate',
                        'description' => 'Japanese manga artist',
                        'detailedDescription' => [
                            'articleBody' => 'Haruichi Furudate is a Japanese manga artist.',
                            'url' => 'https://en.wikipedia.org/wiki/Haruichi_Furudate',
                        ],
                        'image' => [
                            'contentUrl' => 'http://images.example.test/furudate.jpg',
                            'license' => 'https://creativecommons.org/licenses/by-sa/4.0/',
                        ],
                    ],
                ]],
            ]),
        ]);
        $author = Author::factory()->create([
            'name' => 'Haruichi Furudate',
            'slug' => 'haruichi-furudate',
            'biography' => null,
            'image_url' => null,
        ]);

        $this->get(route('authors.show', $author))
            ->assertOk()
            ->assertSeeText('Haruichi Furudate is a Japanese manga artist.')
            ->assertSee('https://images.example.test/furudate.jpg', false)
            ->assertSee('https://en.wikipedia.org/wiki/Haruichi_Furudate', false)
            ->assertSee('https://creativecommons.org/licenses/by-sa/4.0/', false);

        $this->assertDatabaseHas('authors', [
            'id' => $author->id,
            'biography' => 'Haruichi Furudate is a Japanese manga artist.',
            'image_url' => 'https://images.example.test/furudate.jpg',
        ]);
        Http::assertSent(fn ($request): bool => $request['query'] === 'Haruichi Furudate author'
            && $request['types'] === 'Person');
    }

    public function test_author_page_persists_wikipedia_portrait_when_knowledge_graph_has_no_image(): void
    {
        Cache::flush();
        $this->configureKnowledgeGraph();
        Http::preventStrayRequests();
        Http::fake([
            'https://kgsearch.googleapis.com/v1/entities:search*' => Http::response([
                'itemListElement' => [[
                    'resultScore' => 990,
                    'result' => [
                        '@id' => 'kg:/m/author',
                        '@type' => ['Thing', 'Person'],
                        'name' => 'Haruichi Furudate',
                        'description' => 'Japanese manga artist',
                        'detailedDescription' => [
                            'articleBody' => 'Haruichi Furudate is a Japanese manga artist.',
                            'url' => 'https://en.wikipedia.org/wiki/Haruichi_Furudate',
                        ],
                    ],
                ]],
            ]),
            'https://en.wikipedia.org/api/rest_v1/page/summary/Haruichi_Furudate' => Http::response([
                'originalimage' => [
                    'source' => 'https://upload.wikimedia.org/wikipedia/commons/furudate.jpg',
                ],
                'content_urls' => [
                    'desktop' => [
                        'page' => 'https://en.wikipedia.org/wiki/Haruichi_Furudate',
                    ],
                ],
            ]),
        ]);
        $author = Author::factory()->create([
            'name' => 'Haruichi Furudate',
            'slug' => 'haruichi-furudate',
            'biography' => null,
            'image_url' => null,
        ]);

        $this->get(route('authors.show', $author))
            ->assertOk()
            ->assertSee('https://upload.wikimedia.org/wikipedia/commons/furudate.jpg', false);

        $this->assertDatabaseHas('authors', [
            'id' => $author->id,
            'image_url' => 'https://upload.wikimedia.org/wikipedia/commons/furudate.jpg',
        ]);
        Http::assertSentCount(2);
    }

    public function test_author_search_result_links_to_the_author_page(): void
    {
        $author = Author::factory()->create([
            'name' => 'Haruichi Furudate',
            'slug' => 'haruichi-furudate',
        ]);

        $this->get(route('search.index', ['q' => 'Haruichi']))
            ->assertOk()
            ->assertSee(route('authors.show', $author), false);
    }

    private function configureKnowledgeGraph(): void
    {
        config()->set([
            'services.knowledge_graph.base_url' => 'https://kgsearch.googleapis.com/v1/entities:search',
            'services.knowledge_graph.key' => 'test-knowledge-graph-key',
            'services.knowledge_graph.language' => 'en',
            'services.knowledge_graph.candidate_limit' => 5,
            'services.knowledge_graph.cache_days' => 30,
            'services.knowledge_graph.connect_timeout' => 1,
            'services.knowledge_graph.timeout' => 2,
        ]);
    }
}
