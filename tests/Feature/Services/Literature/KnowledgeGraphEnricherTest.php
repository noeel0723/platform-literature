<?php

namespace Tests\Feature\Services\Literature;

use App\Services\Literature\KnowledgeGraphEnricher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KnowledgeGraphEnricherTest extends TestCase
{
    public function test_exact_literary_entity_returns_structured_enrichment(): void
    {
        $this->configureKnowledgeGraph();
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake([
            'https://kgsearch.googleapis.com/v1/entities:search*' => Http::response([
                '@type' => 'ItemList',
                'itemListElement' => [[
                    '@type' => 'EntitySearchResult',
                    'resultScore' => 975.5,
                    'result' => [
                        '@id' => 'kg:/m/01yn7v',
                        '@type' => ['Thing', 'Book'],
                        'name' => 'Dune',
                        'description' => '1965 novel by Frank Herbert',
                        'detailedDescription' => [
                            'articleBody' => 'Dune is a science fiction novel by American author Frank Herbert.',
                            'url' => 'https://en.wikipedia.org/wiki/Dune_(novel)',
                        ],
                        'url' => 'https://dunenovels.com/',
                        'image' => ['contentUrl' => 'https://images.example.test/dune.jpg'],
                    ],
                ]],
            ]),
        ]);

        $entity = app(KnowledgeGraphEnricher::class)->find('Dune', ['Frank Herbert']);

        $this->assertNotNull($entity);
        $this->assertSame('kg:/m/01yn7v', $entity->id);
        $this->assertSame('Dune', $entity->name);
        $this->assertSame(['Thing', 'Book'], $entity->types);
        $this->assertSame('1965 novel by Frank Herbert', $entity->description);
        $this->assertSame('Dune is a science fiction novel by American author Frank Herbert.', $entity->detailedDescription);
        $this->assertSame('https://en.wikipedia.org/wiki/Dune_(novel)', $entity->sourceUrl);
        $this->assertSame('https://dunenovels.com/', $entity->officialUrl);
        $this->assertSame(975.5, $entity->score);
        $this->assertSame('https://images.example.test/dune.jpg', $entity->imageUrl);
        Http::assertSent(fn ($request): bool => str_starts_with(
            $request->url(),
            'https://kgsearch.googleapis.com/v1/entities:search?',
        )
            && $request['query'] === 'Dune Frank Herbert'
            && $request['languages'] === 'en'
            && $request['limit'] === 5
            && $request['key'] === 'test-knowledge-graph-key');
    }

    public function test_exact_non_literary_entity_is_ignored(): void
    {
        $this->configureKnowledgeGraph();
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake([
            'https://kgsearch.googleapis.com/v1/entities:search*' => Http::response([
                'itemListElement' => [[
                    'resultScore' => 1000,
                    'result' => [
                        '@id' => 'kg:/m/person',
                        '@type' => ['Thing', 'Person'],
                        'name' => 'Dune',
                        'description' => 'Stage name of a musician',
                    ],
                ]],
            ]),
        ]);

        $entity = app(KnowledgeGraphEnricher::class)->find('Dune', ['Frank Herbert']);

        $this->assertNull($entity);
        Http::assertSentCount(1);
    }

    public function test_missing_key_skips_the_remote_request(): void
    {
        config()->set('services.knowledge_graph.key', null);
        Http::preventStrayRequests();

        $entity = app(KnowledgeGraphEnricher::class)->find('Dune', ['Frank Herbert']);

        $this->assertNull($entity);
        Http::assertNothingSent();
    }

    public function test_author_lookup_returns_person_metadata_and_secure_image_url(): void
    {
        $this->configureKnowledgeGraph();
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake([
            'https://kgsearch.googleapis.com/v1/entities:search*' => Http::response([
                'itemListElement' => [[
                    'resultScore' => 870,
                    'result' => [
                        '@id' => 'kg:/m/author',
                        '@type' => ['Thing', 'Person'],
                        'name' => 'Hiromu Arakawa',
                        'description' => 'Japanese manga artist',
                        'detailedDescription' => [
                            'articleBody' => 'Hiromu Arakawa is a Japanese manga artist.',
                            'url' => 'https://en.wikipedia.org/wiki/Hiromu_Arakawa',
                        ],
                        'image' => ['contentUrl' => 'http://images.example.test/arakawa.jpg'],
                    ],
                ]],
            ]),
        ]);

        $entity = app(KnowledgeGraphEnricher::class)->findAuthor('Hiromu Arakawa');

        $this->assertNotNull($entity);
        $this->assertSame('Hiromu Arakawa is a Japanese manga artist.', $entity->detailedDescription);
        $this->assertSame('https://images.example.test/arakawa.jpg', $entity->imageUrl);
        Http::assertSent(fn ($request): bool => $request['query'] === 'Hiromu Arakawa author'
            && $request['types'] === 'Person');
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
