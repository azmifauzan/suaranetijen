<?php

namespace Database\Factories;

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\EntityCandidate;
use App\Domains\Entities\Models\EntitySubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntitySubmission>
 */
class EntitySubmissionFactory extends Factory
{
    protected $model = EntitySubmission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'entity_candidate_id' => EntityCandidate::factory(),
            'name' => fake()->unique()->company(),
            'entity_type' => 'brand',
            'category_id' => Category::factory(),
            'is_owner' => false,
        ];
    }
}
