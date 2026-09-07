<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'reportable_type' => Review::class,
            'reportable_id' => Review::factory(),
            'reason' => fake()->randomElement(array_keys(Report::REASON_LABELS)),
            'details' => fake()->optional()->sentence(),
            'status' => 'pending',
            'resolved_by' => null,
            'resolved_at' => null,
            'resolution_note' => null,
        ];
    }
}
