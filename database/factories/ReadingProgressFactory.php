<?php

namespace Database\Factories;

use App\Models\ReadingList;
use App\Models\ReadingProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingProgress>
 */
class ReadingProgressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reading_list_id' => ReadingList::factory(),
            'current_value' => fake()->numberBetween(1, 100),
            'total_value' => 100,
            'unit' => 'page',
        ];
    }
}
