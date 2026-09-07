<?php

namespace Database\Factories;

use App\Models\Literature;
use App\Models\LiteratureRelation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiteratureRelation>
 */
class LiteratureRelationFactory extends Factory
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
            'related_literature_id' => Literature::factory(),
            'relation_type' => fake()->randomElement(array_keys(LiteratureRelation::TYPE_LABELS)),
            'source' => 'Internal catalog',
        ];
    }
}
