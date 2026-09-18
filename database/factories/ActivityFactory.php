<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Literature;
use App\Models\ReadingList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Activity $activity): void {
            if ($activity->type !== Activity::TYPE_COMPLETED) {
                return;
            }

            ReadingList::query()->firstOrCreate(
                [
                    'user_id' => $activity->user_id,
                    'literature_id' => $activity->literature_id,
                ],
                [
                    'status' => 'completed',
                    'started_at' => $activity->occurred_at,
                    'completed_at' => $activity->occurred_at,
                ],
            );
        });
    }

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
