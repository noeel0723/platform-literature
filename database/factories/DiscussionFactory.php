<?php

namespace Database\Factories;

use App\Models\Discussion;
use App\Models\Literature;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Discussion>
 */
class DiscussionFactory extends Factory
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
            'title' => fake()->sentence(5),
            'body' => fake()->paragraphs(2, true),
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
