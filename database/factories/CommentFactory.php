<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Discussion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
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
            'discussion_id' => Discussion::factory(),
            'parent_id' => null,
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
