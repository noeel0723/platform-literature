<?php

namespace Database\Factories;

use App\Models\Literature;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'literature_id' => Literature::factory(),
            'rating' => fake()->numberBetween(1, 10) / 2,
            'body' => fake()->paragraph(),
            'contains_spoiler' => false,
        ];
    }

    public function spoiler(): static
    {
        return $this->state(fn (array $attributes): array => [
            'contains_spoiler' => true,
        ]);
    }
}
