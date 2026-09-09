<?php

namespace Database\Factories;

use App\Models\CanonicalWork;
use App\Models\CanonicalWorkIdentifier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CanonicalWorkIdentifier>
 */
class CanonicalWorkIdentifierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'canonical_work_id' => CanonicalWork::factory(),
            'scheme' => 'isbn',
            'value' => fake()->unique()->isbn13(),
            'source_key' => 'google-books',
        ];
    }
}
