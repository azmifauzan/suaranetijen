<?php

namespace Database\Factories;

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityReviewVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntityReviewVideo>
 */
class EntityReviewVideoFactory extends Factory
{
    protected $model = EntityReviewVideo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => Entity::factory(),
            'youtube_id' => fake()->unique()->regexify('[A-Za-z0-9_-]{11}'),
            'title' => 'Review '.fake()->word().' '.fake()->word(),
            'published_at' => now()->subDays(fake()->numberBetween(1, 60)),
        ];
    }
}
