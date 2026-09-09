<?php

namespace Database\Factories;

use App\Models\ApiSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiSource>
 */
class ApiSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->company(),
            'base_url' => fake()->url(),
            'supported_types' => ['novel'],
            'is_active' => true,
        ];
    }
}
