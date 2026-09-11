<?php

namespace Tests\Feature;

use App\Models\ApiSource;
use App\Models\Author;
use App\Models\Literature;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
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
            ->assertInertia(fn (Assert $page) => $page
                ->component('Author/Show')
                ->where('author.name', 'Haruichi Furudate')
                ->where('author.biography', 'Japanese manga artist known for Haikyu!!')
                ->where('author.image_url', 'https://images.example.test/furudate.jpg')
                ->has('literatures.data', 1)
                ->where('literatures.data.0.title', 'Haikyu!!')
                ->where('literatures.data.0.url', route('literatures.show', $haikyu)));
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
            ->assertInertia(fn (Assert $page) => $page
                ->component('Author/Show')
                ->where('author.biography', 'Haruichi Furudate is a Japanese manga artist.')
                ->where('author.image_url', 'https://images.example.test/furudate.jpg')
                ->where('profileSourceUrl', 'https://en.wikipedia.org/wiki/Haruichi_Furudate')
                ->where('imageLicenseUrl', 'https://creativecommons.org/licenses/by-sa/4.0/'));

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
            ->assertInertia(fn (Assert $page) => $page
                ->component('Author/Show')
                ->where('author.image_url', 'https://upload.wikimedia.org/wikipedia/commons/furudate.jpg'));

        $this->assertDatabaseHas('authors', [
            'id' => $author->id,
            'image_url' => 'https://upload.wikimedia.org/wikipedia/commons/furudate.jpg',
        ]);
        Http::assertSentCount(2);
    }

    public function test_manga_author_page_uses_anilist_staff_portrait_when_other_metadata_has_no_image(): void
    {
        Cache::flush();
        config()->set('services.knowledge_graph.key', null);
        config()->set([
            'services.anilist.base_url' => 'https://graphql.anilist.co',
            'services.anilist.author_cache_days' => 30,
            'services.anilist.connect_timeout' => 1,
            'services.anilist.timeout' => 2,
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://graphql.anilist.co*' => Http::response([
                'data' => [
                    'Page' => [
                        'staff' => [[
                            'id' => 96879,
                            'name' => [
                                'full' => 'Hiromu Arakawa',
                                'native' => '荒川 弘',
                                'userPreferred' => 'Hiromu Arakawa',
                                'alternative' => [],
                            ],
                            'image' => ['large' => 'https://s4.anilist.co/hiromu-arakawa.jpg'],
                            'description' => 'Japanese manga artist.',
                            'siteUrl' => 'https://anilist.co/staff/96879/Hiromu-Arakawa',
                        ]],
                    ],
                ],
            ]),
        ]);
        $source = ApiSource::factory()->create(['name' => 'AniList']);
        $author = Author::factory()->create([
            'name' => 'Hiromu Arakawa',
            'slug' => 'hiromu-arakawa',
            'biography' => null,
            'image_url' => null,
        ]);
        $literature = Literature::factory()->for($source)->create([
            'title' => 'Fullmetal Alchemist',
            'slug' => 'fullmetal-alchemist',
            'type' => 'manga',
        ]);
        $author->literatures()->attach($literature, ['role' => 'author', 'position' => 0]);

        $this->get(route('authors.show', $author))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Author/Show')
                ->where('author.image_url', 'https://s4.anilist.co/hiromu-arakawa.jpg')
                ->where('author.biography', 'Japanese manga artist.')
                ->where('profileSourceUrl', 'https://anilist.co/staff/96879/Hiromu-Arakawa'));

        $this->assertDatabaseHas('authors', [
            'id' => $author->id,
            'image_url' => 'https://s4.anilist.co/hiromu-arakawa.jpg',
            'biography' => 'Japanese manga artist.',
        ]);
        Http::assertSentCount(1);
    }

    public function test_author_search_result_links_to_the_author_page(): void
    {
        $author = Author::factory()->create([
            'name' => 'Haruichi Furudate',
            'slug' => 'haruichi-furudate',
        ]);

        $this->get(route('search.index', ['q' => 'Haruichi']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('authors.0.url', route('authors.show', $author)));
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
