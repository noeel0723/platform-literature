<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GlobalFooterTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_footer_uses_only_available_public_navigation_routes(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('aria-label="Footer navigation"', false)
            ->assertSee('href="'.route('home').'"', false)
            ->assertSee('href="'.route('literature.index').'"', false)
            ->assertSee('href="'.route('literatures.index').'"', false)
            ->assertSee('&copy; '.now()->year.' Literahaven. All rights reserved.', false)
            ->assertDontSee('>Lists</a>', false)
            ->assertDontSee('>Privacy</a>', false)
            ->assertDontSee('>Terms</a>', false);
    }

    public function test_authenticated_footer_links_to_the_readers_existing_lists_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.route('profiles.lists', $user).'"', false);
    }
}
