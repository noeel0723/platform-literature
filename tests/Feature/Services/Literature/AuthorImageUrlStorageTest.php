<?php

namespace Tests\Feature\Services\Literature;

use App\Services\Literature\AuthorEntityResolver;
use App\Services\Literature\NormalizedAuthor;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthorImageUrlStorageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_long_external_image_url_is_stored_without_truncation(): void
    {
        $imageUrl = 'https://upload.wikimedia.org/wikipedia/commons/'
            .str_repeat('long-portrait-filename-', 20)
            .'.jpg?utm_source=en.wikipedia.org&utm_campaign=api&utm_content=thumbnail_unscaled';

        $author = app(AuthorEntityResolver::class)->resolve(
            'knowledge-graph',
            new NormalizedAuthor(
                name: 'Agatha Christie',
                imageUrl: $imageUrl,
                externalId: 'kg:/m/0ldd',
            ),
        );

        $this->assertSame('text', Schema::getColumnType('authors', 'image_url'));
        $this->assertSame($imageUrl, $author->refresh()->image_url);
    }
}
