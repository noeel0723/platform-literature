<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\AuthorAlias;
use App\Services\Literature\AuthorNameNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuthorAlias>
 */
class AuthorAliasFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'author_id' => Author::factory(),
            'name' => $name,
            'normalized_name' => app(AuthorNameNormalizer::class)->normalize($name),
            'source' => fake()->randomElement(['google-books', 'anilist', 'mangadex', 'kitsu']),
            'external_id' => fake()->unique()->uuid(),
            'source_url' => null,
        ];
    }
}
