<?php

namespace Database\Factories;

use App\Domains\Entities\Models\Entity;
use App\Domains\Ratings\Models\UserRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserRating>
 */
class UserRatingFactory extends Factory
{
    protected $model = UserRating::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'entity_id' => Entity::factory(),
            'rating' => fake()->numberBetween(1, 5),
        ];
    }
}
