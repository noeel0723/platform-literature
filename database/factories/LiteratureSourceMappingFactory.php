<?php

namespace Database\Factories;

use App\Models\CanonicalWork;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiteratureSourceMapping>
 */
class LiteratureSourceMappingFactory extends Factory
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
            'literature_id' => Literature::factory(),
            'api_source_id' => fn (array $attributes): int => Literature::query()
                ->findOrFail($attributes['literature_id'])
                ->api_source_id,
            'source_external_id' => fake()->unique()->bothify('source-####'),
            'match_method' => 'created',
            'mapping_status' => LiteratureSourceMapping::STATUS_NEW,
            'confidence' => 1,
            'quality_score' => 0,
            'candidate_work_ids' => null,
            'field_provenance' => ['title' => 'fixture'],
        ];
    }
}
