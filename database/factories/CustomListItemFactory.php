<?php

namespace Database\Factories;

use App\Models\CanonicalWork;
use App\Models\CustomList;
use App\Models\CustomListItem;
use App\Models\Literature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomListItem>
 */
class CustomListItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'custom_list_id' => CustomList::factory(),
            'canonical_work_id' => CanonicalWork::factory(),
            'literature_id' => Literature::factory(),
            'position' => fake()->unique()->numberBetween(1, 10000),
        ];
    }
}
