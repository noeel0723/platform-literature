<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GlobalFooterTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_footer_shows_grouped_data_sources_and_copyright_without_navigation(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Data sources')
            ->assertSee('Literature catalog')
            ->assertSee('Supporting metadata')
            ->assertSee('href="https://hardcover.app/"', false)
            ->assertSee('href="https://books.google.com/"', false)
            ->assertSee('href="https://anilist.co/"', false)
            ->assertSee('href="https://kitsu.io/"', false)
            ->assertSee('href="https://comicvine.gamespot.com/"', false)
            ->assertSee('href="https://www.mangaupdates.com/"', false)
            ->assertSee('href="https://www.wikidata.org/"', false)
            ->assertSee('href="https://www.wikipedia.org/"', false)
            ->assertSee('href="https://developers.google.com/knowledge-graph"', false)
            ->assertSee('&copy; '.now()->year.' Literahaven. All rights reserved.', false)
            ->assertDontSee('aria-label="Footer navigation"', false)
            ->assertDontSee('>Lists</a>', false)
            ->assertDontSee('>Privacy</a>', false)
            ->assertDontSee('>Terms</a>', false);
    }

    public function test_authenticated_footer_keeps_data_sources_without_the_removed_navigation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertSee('Data sources')
            ->assertSee('Literature catalog')
            ->assertSee('Supporting metadata')
            ->assertSee('&copy; '.now()->year.' Literahaven. All rights reserved.', false)
            ->assertDontSee('aria-label="Footer navigation"', false);
    }
}
