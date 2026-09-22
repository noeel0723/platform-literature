<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\CanonicalWork;
use App\Models\Literature;
use App\Models\LiteratureMetadataOverride;
use App\Models\LiteratureSourceMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeLandingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_home_uses_a_canonical_global_popular_work_for_the_hero_and_weekly_list(): void
    {
        Http::preventStrayRequests();
        $preferred = Literature::factory()->create([
            'title' => 'Canonical Hero',
            'backdrop_url' => 'https://images.example.test/canonical-hero.jpg',
        ]);
        $sibling = Literature::factory()->create(['title' => 'Canonical Hero Sibling']);
        $this->canonicalize($preferred, $sibling);
        Activity::factory()
            ->for(User::factory())
            ->for($sibling)
            ->create(['type' => Activity::TYPE_STARTED_READING]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home/Index')
                ->where('viewer', null)
                ->where('guestHero.title', 'Canonical Hero')
                ->where('guestHero.backdrop_url', 'https://images.example.test/canonical-hero.jpg')
                ->has('popularThisWeek', 1)
                ->where('popularThisWeek.0.title', 'Canonical Hero')
                ->where('routes.register', route('register'))
                ->where('routes.literature', route('literature.browse', ['sort' => 'popularity']))
                ->has('activities', 0)
                ->has('popularLiteratures', 0));
    }

    public function test_guest_hero_skips_a_more_popular_work_without_a_backdrop(): void
    {
        $withoutBackdrop = Literature::factory()->create(['title' => 'Most Popular Without Artwork']);
        $withBackdrop = Literature::factory()->create([
            'title' => 'Popular With Artwork',
            'backdrop_url' => 'https://images.example.test/popular-artwork.jpg',
        ]);

        User::factory()->count(3)->create()->each(
            function (User $reader) use ($withoutBackdrop): void {
                Activity::factory()
                    ->for($reader)
                    ->for($withoutBackdrop)
                    ->create(['type' => Activity::TYPE_STARTED_READING]);
            },
        );
        Activity::factory()
            ->for(User::factory())
            ->for($withBackdrop)
            ->create(['type' => Activity::TYPE_STARTED_READING]);

        $response = $this->get(route('home'));

        $this->assertSame('Most Popular Without Artwork', $response->inertiaProps('popularThisWeek.0.title'));
        $this->assertSame('Popular With Artwork', $response->inertiaProps('guestHero.title'));
    }

    public function test_guest_hero_does_not_use_a_portrait_cover_as_a_backdrop_fallback(): void
    {
        $literature = Literature::factory()->create([
            'title' => 'Portrait Cover Only',
            'cover_url' => 'https://images.example.test/portrait.jpg',
            'backdrop_url' => null,
        ]);
        Activity::factory()
            ->for(User::factory())
            ->for($literature)
            ->create(['type' => Activity::TYPE_STARTED_READING]);

        $response = $this->get(route('home'));

        $this->assertNull($response->inertiaProps('guestHero'));
        $this->assertSame('https://images.example.test/portrait.jpg', $response->inertiaProps('popularThisWeek.0.cover_url'));
    }

    public function test_guest_hero_respects_a_curated_canonical_backdrop(): void
    {
        $editor = User::factory()->create();
        $preferred = Literature::factory()->create([
            'title' => 'Curated Hero',
            'backdrop_url' => 'https://images.example.test/provider-hero.jpg',
        ]);
        $sibling = Literature::factory()->create(['title' => 'Curated Hero Sibling']);
        $canonicalWork = $this->canonicalize($preferred, $sibling);
        LiteratureMetadataOverride::factory()->create([
            'literature_id' => $preferred->id,
            'canonical_work_id' => $canonicalWork->id,
            'edited_by' => $editor->id,
            'backdrop_url' => 'https://images.example.test/curated-hero.jpg',
        ]);
        Activity::factory()
            ->for(User::factory())
            ->for($sibling)
            ->create(['type' => Activity::TYPE_STARTED_READING]);

        $response = $this->get(route('home'));

        $this->assertSame('https://images.example.test/curated-hero.jpg', $response->inertiaProps('guestHero.backdrop_url'));
    }

    public function test_guest_popular_this_week_is_limited_to_six_works(): void
    {
        Literature::factory()->count(7)->create()->each(function (Literature $literature): void {
            Activity::factory()
                ->for(User::factory())
                ->for($literature)
                ->create(['type' => Activity::TYPE_STARTED_READING]);
        });

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->has('popularThisWeek', 6));
    }

    public function test_authenticated_home_keeps_the_existing_personalized_data_and_omits_guest_landing_data(): void
    {
        $viewer = User::factory()->create();
        $friend = User::factory()->create();
        $viewer->following()->attach($friend);
        $literature = Literature::factory()->create([
            'title' => 'Friend Home Activity',
            'backdrop_url' => 'https://images.example.test/friend-hero.jpg',
        ]);
        Activity::factory()->for($friend)->for($literature)->create([
            'type' => Activity::TYPE_COMPLETED,
        ]);

        $this->actingAs($viewer)->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewer.name', $viewer->name)
                ->where('activities.0.literature.title', 'Friend Home Activity')
                ->where('guestHero', null)
                ->has('popularThisWeek', 0));
    }

    public function test_header_marks_only_the_guest_home_as_a_guest_landing(): void
    {
        $guestHome = $this->get(route('home'));
        $guestHomeProps = $this->embeddedReactProps($guestHome->getContent(), 'data-react-header-props');

        $this->assertTrue($guestHomeProps['is_guest_landing']);

        $literaturePage = $this->get(route('literature.index'));
        $literatureHeaderProps = $this->embeddedReactProps($literaturePage->getContent(), 'data-react-header-props');

        $this->assertFalse($literatureHeaderProps['is_guest_landing']);

        $viewer = User::factory()->create();
        $authenticatedHome = $this->actingAs($viewer)->get(route('home'));
        $authenticatedHeaderProps = $this->embeddedReactProps($authenticatedHome->getContent(), 'data-react-header-props');

        $this->assertFalse($authenticatedHeaderProps['is_guest_landing']);
    }

    private function canonicalize(Literature $preferred, Literature $sibling): CanonicalWork
    {
        $canonicalWork = CanonicalWork::factory()->create([
            'preferred_literature_id' => $preferred->id,
            'canonical_title' => $preferred->title,
            'normalized_title' => str($preferred->title)->lower()->toString(),
            'type' => $preferred->type,
            'publication_year' => $preferred->publication_year,
        ]);

        foreach ([$preferred, $sibling] as $index => $literature) {
            LiteratureSourceMapping::factory()
                ->for($canonicalWork)
                ->for($literature)
                ->for($literature->apiSource)
                ->create(['source_external_id' => "home-landing-{$index}-{$literature->id}"]);
        }

        return $canonicalWork;
    }

    /** @return array<string, mixed> */
    private function embeddedReactProps(string $content, string $attribute): array
    {
        $matched = preg_match('/<script[^>]*'.preg_quote($attribute, '/').'[^>]*>(.*?)<\/script>/s', $content, $matches);

        $this->assertSame(1, $matched);

        return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    }
}
