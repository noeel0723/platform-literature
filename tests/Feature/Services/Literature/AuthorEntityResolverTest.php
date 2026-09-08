<?php

namespace Tests\Feature\Services\Literature;

use App\Models\ApiSource;
use App\Models\Author;
use App\Models\Literature;
use App\Models\User;
use App\Services\Literature\AuthorEntityResolver;
use App\Services\Literature\KnowledgeGraphEntity;
use App\Services\Literature\NormalizedAuthor;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthorEntityResolverTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_punctuation_and_initial_spacing_variants_resolve_to_one_canonical_author(): void
    {
        $resolver = app(AuthorEntityResolver::class);

        $authors = collect(['J.K Rowling', 'J.K. Rowling', 'J. K. Rowling', 'JK Rowling'])
            ->map(fn (string $name): Author => $resolver->resolve(
                'google-books',
                new NormalizedAuthor(name: $name),
            ));

        $this->assertSame(1, $authors->pluck('id')->unique()->count());
        $this->assertDatabaseCount('authors', 1);
        $this->assertDatabaseCount('author_aliases', 4);
        $this->assertDatabaseHas('authors', [
            'name' => 'J.K Rowling',
            'normalized_name' => 'jk rowling',
        ]);
    }

    public function test_external_entity_identity_is_stronger_than_a_different_alias_name(): void
    {
        $resolver = app(AuthorEntityResolver::class);
        $entity = new KnowledgeGraphEntity(
            id: 'kg:/m/042xh',
            name: 'J. K. Rowling',
            types: ['Thing', 'Person'],
            description: 'British author',
            detailedDescription: null,
            sourceUrl: 'https://en.wikipedia.org/wiki/J._K._Rowling',
            officialUrl: null,
            score: 1200,
        );
        $canonical = $resolver->resolve(
            'google-books',
            new NormalizedAuthor(name: 'J. K. Rowling'),
            $entity,
        );

        $resolvedAlias = $resolver->resolve(
            'anilist',
            new NormalizedAuthor(name: 'Robert Galbraith', externalId: '98123'),
            $entity,
        );

        $this->assertTrue($canonical->is($resolvedAlias));
        $this->assertSame('J. K. Rowling', $resolvedAlias->name);
        $this->assertDatabaseCount('authors', 1);
        $this->assertDatabaseHas('author_aliases', [
            'author_id' => $canonical->id,
            'name' => 'Robert Galbraith',
            'source' => 'anilist',
            'external_id' => '98123',
        ]);
    }

    public function test_provider_identity_resolves_a_renamed_author_without_fuzzy_matching(): void
    {
        $resolver = app(AuthorEntityResolver::class);
        $canonical = $resolver->resolve(
            'mangadex',
            new NormalizedAuthor(name: 'ONE', externalId: 'creator-123'),
        );

        $resolvedName = $resolver->resolve(
            'mangadex',
            new NormalizedAuthor(name: 'Tomohiro', externalId: 'creator-123'),
        );

        $this->assertTrue($canonical->is($resolvedName));
        $this->assertSame('ONE', $resolvedName->name);
        $this->assertDatabaseCount('authors', 1);
    }

    public function test_similar_names_are_not_fuzzy_merged(): void
    {
        $resolver = app(AuthorEntityResolver::class);

        $john = $resolver->resolve('google-books', new NormalizedAuthor(name: 'John Smith'));
        $jon = $resolver->resolve('google-books', new NormalizedAuthor(name: 'Jon Smith'));

        $this->assertFalse($john->is($jon));
        $this->assertDatabaseCount('authors', 2);
    }

    public function test_resolving_a_legacy_author_preserves_relations_favorites_and_metadata(): void
    {
        $source = ApiSource::factory()->create();
        $literature = Literature::factory()->for($source)->create();
        $author = Author::factory()->create([
            'name' => 'J.K. Rowling',
            'normalized_name' => null,
            'biography' => 'Existing biography',
            'image_url' => 'https://example.com/existing.jpg',
        ]);
        $literature->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        $user = User::factory()->create();
        $user->favoriteAuthors()->attach($author, ['position' => 0]);

        $resolved = app(AuthorEntityResolver::class)->resolve(
            'anilist',
            new NormalizedAuthor(
                name: 'JK Rowling',
                imageUrl: 'https://example.com/replacement.jpg',
                biography: 'Replacement biography',
                externalId: '1234',
            ),
        );

        $this->assertTrue($author->is($resolved));
        $this->assertSame('Existing biography', $resolved->biography);
        $this->assertSame('https://example.com/existing.jpg', $resolved->image_url);
        $this->assertTrue($resolved->literatures()->whereKey($literature->id)->exists());
        $this->assertTrue($resolved->favoritedByUsers()->whereKey($user->id)->exists());
        $this->assertDatabaseCount('authors', 1);
    }
}
