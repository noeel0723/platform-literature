<?php

namespace Database\Factories;

use App\Models\Literature;
use App\Models\ReadingList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingList>
 */
class ReadingListFactory extends Factory
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
            'status' => 'want_to_read',
            'started_at' => null,
            'completed_at' => null,
            'reread_count' => 0,
        ];
    }
}
