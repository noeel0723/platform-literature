<?php

namespace Tests\Unit\Models;

use App\Models\Literature;
use PHPUnit\Framework\TestCase;

class LiteratureTest extends TestCase
{
    public function test_manga_and_manhwa_display_the_global_title_and_keep_the_native_title_as_an_alternative(): void
    {
        foreach (['manga', 'manhwa'] as $type) {
            $literature = new Literature([
                'type' => $type,
                'title' => 'Global English Title',
                'original_title' => '原作タイトル',
            ]);

            $this->assertSame('Global English Title', $literature->displayTitle());
            $this->assertSame('原作タイトル', $literature->alternateTitle());
        }
    }

    public function test_novel_keeps_the_enriched_canonical_title_as_its_display_title(): void
    {
        $literature = new Literature([
            'type' => 'novel',
            'title' => 'Localized Edition Title',
            'original_title' => 'Canonical English Title',
        ]);

        $this->assertSame('Canonical English Title', $literature->displayTitle());
        $this->assertSame('Localized Edition Title', $literature->alternateTitle());
    }
}
