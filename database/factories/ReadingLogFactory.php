<?php

namespace Database\Factories;

use App\Models\ReadingList;
use App\Models\ReadingLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingLog>
 */
class ReadingLogFactory extends Factory
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
            'event_type' => 'started',
            'status' => 'reading',
            'progress_value' => null,
            'progress_total' => null,
            'progress_unit' => null,
            'note' => null,
            'occurred_at' => now(),
        ];
    }
}
