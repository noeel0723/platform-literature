<?php

namespace Tests\Feature;

use Tests\TestCase;

class LiteratureCatalogTest extends TestCase
{
    public function test_catalog_home_renders_increment_one_interface(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSeeText('Satu rak untuk setiap cerita')
            ->assertSeeText('Bumi Manusia')
            ->assertSeeText('Google Books');
    }

    public function test_catalog_can_be_filtered_by_query_and_type(): void
    {
        $response = $this->get(route('literatures.index', [
            'q' => 'bumi',
            'type' => 'book',
        ]));

        $response
            ->assertOk()
            ->assertSeeText('Bumi Manusia')
            ->assertDontSeeText('Fullmetal Alchemist');
    }

    public function test_literature_detail_renders_catalog_metadata(): void
    {
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
}
