<?php

namespace Database\Factories;

use App\Models\Literature;
use App\Models\LiteratureMetadataOverride;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiteratureMetadataOverride>
 */
class LiteratureMetadataOverrideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'literature_id' => Literature::factory(),
            'edited_by' => User::factory(),
            'title' => fake()->sentence(4),
            'synopsis' => fake()->paragraph(),
        ];
    }
}
