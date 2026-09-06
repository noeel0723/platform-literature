<?php

namespace Database\Factories;

use App\Models\ApiSource;
use App\Models\Literature;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Literature>
 */
class LiteratureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'api_source_id' => ApiSource::factory(),
            'external_id' => fake()->unique()->bothify('external-####'),
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(100, 999),
            'title' => $title,
            'original_title' => null,
            'type' => 'book',
            'publication_year' => fake()->numberBetween(1900, 2026),
            'tagline' => fake()->sentence(),
            'synopsis' => fake()->paragraph(),
            'publisher' => fake()->company(),
            'language' => 'en',
            'format' => 'Novel',
            'identifier' => fake()->unique()->isbn13(),
            'cover_url' => null,
            'theme' => 'cream',
        ];
    }
}
