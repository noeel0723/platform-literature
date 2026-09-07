<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Literature;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
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
            'review_id' => null,
            'type' => Activity::TYPE_STARTED_READING,
            'metadata' => null,
            'occurred_at' => now(),
        ];
    }
}
