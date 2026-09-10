<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Literature;
use App\Models\LiteratureRelation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LiteratureDiscoveryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_detail_page_groups_confirmed_cross_format_relationships(): void
    {
        $literature = Literature::factory()->create([
            'slug' => 'the-main-story',
            'title' => 'The Main Story',
        ]);
        $sequel = Literature::factory()->create([
            'slug' => 'the-next-story',
            'title' => 'The Next Story',
            'type' => 'manga',
        ]);
        $adaptation = Literature::factory()->create([
            'slug' => 'the-graphic-adaptation',
            'title' => 'The Graphic Adaptation',
            'type' => 'western-comic',
        ]);
        LiteratureRelation::factory()->create([
            'literature_id' => $literature->id,
            'related_literature_id' => $sequel->id,
            'relation_type' => 'sequel',
            'source' => 'AniList',
        ]);
        LiteratureRelation::factory()->create([
            'literature_id' => $literature->id,
            'related_literature_id' => $adaptation->id,
            'relation_type' => 'adaptation',
            'source' => 'Internal catalog',
        ]);

        $response = $this->get(route('literatures.show', $literature));

        $groups = collect($response->inertiaProps('relationshipGroups'))->keyBy('type');

        $response->assertOk();
        $this->assertSame('Catalog/Show', $response->inertiaPage()['component']);
        $this->assertSame('The Next Story', $groups['sequel']['items'][0]['title']);
        $this->assertSame('AniList', $groups['sequel']['items'][0]['relation_source']);
        $this->assertSame(route('literatures.show', $sequel), $groups['sequel']['items'][0]['url']);
        $this->assertSame('The Graphic Adaptation', $groups['adaptation']['items'][0]['title']);
        $this->assertSame(route('literatures.show', $adaptation), $groups['adaptation']['items'][0]['url']);
    }

    public function test_detail_page_discovers_other_catalog_entries_by_the_same_author(): void
    {
        $author = Author::factory()->create(['name' => 'Octavia Example']);
        $literature = Literature::factory()->create([
            'slug' => 'first-work',
            'title' => 'First Work',
        ]);
        $otherWork = Literature::factory()->create([
            'slug' => 'second-work',
            'title' => 'Second Work',
        ]);
        $unrelatedWork = Literature::factory()->create([
            'slug' => 'unrelated-work',
            'title' => 'Unrelated Work',
        ]);
        $literature->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        $otherWork->authors()->attach($author, ['role' => 'author', 'position' => 0]);

        $response = $this->get(route('literatures.show', $literature));

        $response->assertOk();
        $this->assertSame('Catalog/Show', $response->inertiaPage()['component']);
        $this->assertSame(['Second Work'], collect($response->inertiaProps('authorDiscoveries'))->pluck('title')->all());
        $this->assertSame(route('literatures.show', $otherWork), $response->inertiaProps('authorDiscoveries.0.url'));
    }

    public function test_relation_types_have_navigable_inverse_meanings(): void
    {
        $this->assertSame('prequel', LiteratureRelation::inverseType('sequel'));
        $this->assertSame('sequel', LiteratureRelation::inverseType('prequel'));
        $this->assertSame('source', LiteratureRelation::inverseType('adaptation'));
        $this->assertSame('adaptation', LiteratureRelation::inverseType('source'));
        $this->assertSame('same_series', LiteratureRelation::inverseType('same_series'));
        $this->assertSame('related', LiteratureRelation::inverseType('unknown'));
    }
}
