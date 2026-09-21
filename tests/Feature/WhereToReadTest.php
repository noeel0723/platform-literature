<?php

namespace Tests\Feature;

use App\Models\CanonicalWork;
use App\Models\CanonicalWorkLink;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WhereToReadTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_source_siblings_share_official_where_to_read_links_from_their_canonical_work(): void
    {
        [$preferred, $sibling, $canonicalWork] = $this->canonicalSiblings();
        $link = CanonicalWorkLink::query()->create([
            'canonical_work_id' => $canonicalWork->id,
            'provider' => 'Google Books',
            'url' => 'https://books.google.test/preview/123',
            'link_type' => 'preview',
            'source' => 'google-books',
            'is_official' => true,
            'is_active' => true,
            'verified_at' => now(),
        ]);

        foreach ([$preferred, $sibling] as $literature) {
            $this->get(route('literatures.show', $literature))
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Catalog/Show')
                    ->has('whereToRead', 1)
                    ->where('whereToRead.0.id', $link->id)
                    ->where('whereToRead.0.provider', 'Google Books')
                    ->where('whereToRead.0.link_type', 'preview')
                    ->where('whereToRead.0.url', 'https://books.google.test/preview/123')
                );
        }
    }

    public function test_inactive_non_official_and_unverified_links_are_not_displayed(): void
    {
        [$literature, , $canonicalWork] = $this->canonicalSiblings();

        foreach ([
            ['provider' => 'Inactive', 'is_official' => true, 'is_active' => false, 'verified_at' => now()],
            ['provider' => 'Unofficial', 'is_official' => false, 'is_active' => true, 'verified_at' => now()],
            ['provider' => 'Unverified', 'is_official' => true, 'is_active' => true, 'verified_at' => null],
        ] as $index => $attributes) {
            CanonicalWorkLink::query()->create([
                'canonical_work_id' => $canonicalWork->id,
                'provider' => $attributes['provider'],
                'url' => "https://example.test/link/{$index}",
                'link_type' => 'read',
                'source' => 'curated',
                'is_official' => $attributes['is_official'],
                'is_active' => $attributes['is_active'],
                'verified_at' => $attributes['verified_at'],
            ]);
        }

        $this->get(route('literatures.show', $literature))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalog/Show')
                ->has('whereToRead', 0)
            );
    }

    public function test_edit_metadata_route_is_only_sent_to_admin_viewers(): void
    {
        $literature = Literature::factory()->create();
        $regularUser = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->get(route('literatures.show', $literature))
            ->assertInertia(fn (Assert $page) => $page->where('routes.admin_edit_metadata', null));

        $this->actingAs($regularUser)
            ->get(route('literatures.show', $literature))
            ->assertInertia(fn (Assert $page) => $page->where('routes.admin_edit_metadata', null));

        $this->actingAs($admin)
            ->get(route('literatures.show', $literature))
            ->assertInertia(fn (Assert $page) => $page->where(
                'routes.admin_edit_metadata',
                route('admin.literatures.metadata.edit', $literature),
            ));
    }

    public function test_literature_detail_uses_local_availability_data_without_external_requests(): void
    {
        Http::preventStrayRequests();
        [$literature] = $this->canonicalSiblings();

        $this->get(route('literatures.show', $literature))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Catalog/Show'));

        Http::assertNothingSent();
    }

    /** @return array{Literature, Literature, CanonicalWork} */
    private function canonicalSiblings(): array
    {
        $preferred = Literature::factory()->create(['title' => 'Canonical Edition']);
        $sibling = Literature::factory()->create(['title' => 'Provider Sibling']);
        $canonicalWork = CanonicalWork::factory()->create([
            'preferred_literature_id' => $preferred->id,
        ]);

        foreach ([$preferred, $sibling] as $literature) {
            LiteratureSourceMapping::factory()->create([
                'canonical_work_id' => $canonicalWork->id,
                'literature_id' => $literature->id,
                'api_source_id' => $literature->api_source_id,
                'source_external_id' => $literature->external_id,
            ]);
        }

        return [$preferred, $sibling, $canonicalWork];
    }
}
