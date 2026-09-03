<?php

namespace Database\Factories;

use App\Domains\Entities\Models\Entity;
use App\Domains\Ratings\Models\RatingSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RatingSnapshot>
 */
class RatingSnapshotFactory extends Factory
{
    protected $model = RatingSnapshot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $count = fake()->numberBetween(1, 100);

        return [
            'entity_id' => Entity::factory(),
            'rating_count' => $count,
            'rating_average' => fake()->randomFloat(2, 1, 5),
            'calculated_at' => CarbonImmutable::now(),
        ];
    }
}
