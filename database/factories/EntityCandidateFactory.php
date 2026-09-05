<?php

namespace Database\Factories;

use App\Domains\Entities\Models\EntityCandidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntityCandidate>
 */
class EntityCandidateFactory extends Factory
{
    protected $model = EntityCandidate::class;

    public function definition(): array
    {
        return [
            'normalized_term' => fake()->unique()->word(),
            'raw_terms' => [fake()->word()],
            'source_types' => ['search_query'],
            'frequency_score' => fake()->numberBetween(1, 50),
            'unmatched_mention_count' => 0,
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => 'rejected']);
    }
}
