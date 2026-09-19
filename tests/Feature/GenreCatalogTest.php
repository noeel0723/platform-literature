<?php

namespace Tests\Feature;

use App\Models\CanonicalWork;
use App\Models\Category;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GenreCatalogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_genre_page_paginates_only_matching_literature(): void
    {
        $action = Category::factory()->create(['name' => 'Action', 'slug' => 'action']);
        $romance = Category::factory()->create(['name' => 'Romance', 'slug' => 'romance']);

        foreach (range(1, 19) as $position) {
            $literature = Literature::factory()->create([
                'title' => "Action Work {$position}",
                'slug' => "action-work-{$position}",
                'type' => $position === 1 ? 'western-comic' : 'manga',
            ]);
            $literature->categories()->attach($action);
        }

        $other = Literature::factory()->create(['title' => 'Romance Work']);
        $other->categories()->attach($romance);

        $response = $this->get(route('literatures.genre', $action));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Genre')
            ->where('genre.name', 'Action')
            ->where('genre.slug', 'action')
            ->where('selectedType', '')
            ->where('selectedSort', 'latest')
            ->where('literatures.total', 19)
            ->where('literatures.per_page', 18)
            ->has('literatures.data', 18)
            ->has('routes.genre')
            ->has('routes.catalog'));
        $this->assertNotContains('Romance Work', collect($response->inertiaProps('literatures.data'))->pluck('title')->all());
    }

    public function test_genre_page_is_canonical_aware_when_only_an_alternate_source_has_the_genre(): void
    {
        $action = Category::factory()->create(['name' => 'Action', 'slug' => 'action']);
        $preferred = Literature::factory()->create([
            'title' => 'Canonical Comic',
            'slug' => 'canonical-comic',
            'type' => 'western-comic',
        ]);
        $alternate = Literature::factory()->create([
            'title' => 'Canonical Comic Alternate',
            'slug' => 'canonical-comic-alternate',
            'type' => 'western-comic',
        ]);
        $alternate->categories()->attach($action);
        $work = CanonicalWork::factory()->create([
            'preferred_literature_id' => $preferred->id,
            'canonical_title' => 'Canonical Comic',
            'normalized_title' => 'canonical comic',
            'type' => 'western-comic',
        ]);
        $this->map($work, $preferred, 'preferred');
        $this->map($work, $alternate, 'alternate');

        $response = $this->get(route('literatures.genre', $action));

        $this->assertSame(1, $response->inertiaProps('literatures.total'));
        $this->assertSame('Canonical Comic', $response->inertiaProps('literatures.data.0.title'));
    }

    public function test_literature_detail_exposes_clickable_genres_from_the_canonical_work(): void
    {
        $action = Category::factory()->create(['name' => 'Action', 'slug' => 'action']);
        $preferred = Literature::factory()->create([
            'title' => 'Canonical Manga',
            'slug' => 'canonical-manga',
            'type' => 'manga',
        ]);
        $alternate = Literature::factory()->create([
            'title' => 'Canonical Manga Alternate',
            'slug' => 'canonical-manga-alternate',
            'type' => 'manga',
        ]);
        $alternate->categories()->attach($action);
        $work = CanonicalWork::factory()->create([
            'preferred_literature_id' => $preferred->id,
            'canonical_title' => 'Canonical Manga',
            'normalized_title' => 'canonical manga',
            'type' => 'manga',
        ]);
        $this->map($work, $preferred, 'preferred');
        $this->map($work, $alternate, 'alternate');

        $response = $this->get(route('literatures.show', $preferred));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Show')
            ->where('literature.genre_links.0.name', 'Action')
            ->where('literature.genre_links.0.slug', 'action')
            ->where('literature.genre_links.0.url', route('literatures.genre', $action)));
    }

    public function test_unknown_genre_slug_returns_not_found(): void
    {
        $this->get('/catalog/genre/not-a-real-genre')->assertNotFound();
    }

    private function map(CanonicalWork $work, Literature $literature, string $externalId): void
    {
        LiteratureSourceMapping::factory()
            ->for($work)
            ->for($literature)
            ->for($literature->apiSource)
            ->create(['source_external_id' => $externalId]);
    }
}
