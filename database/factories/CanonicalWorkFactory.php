<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\CanonicalWork;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CanonicalWork>
 */
class CanonicalWorkFactory extends Factory
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
            'primary_author_id' => Author::factory(),
            'canonical_title' => $title,
            'normalized_title' => Str::lower(Str::slug($title, ' ')),
            'type' => 'novel',
            'publication_year' => fake()->numberBetween(1900, 2026),
        ];
    }
}
