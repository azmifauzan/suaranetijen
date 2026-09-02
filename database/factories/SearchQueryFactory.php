<?php

namespace Database\Factories;

use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Search\Models\SearchQuery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchQuery>
 */
class SearchQueryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<SearchQuery>
     */
    protected $model = SearchQuery::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $query = fake()->word().' '.fake()->word();

        return [
            'query' => $query,
            'normalized_query' => TextNormalizer::normalize($query),
            'result_count' => fake()->numberBetween(0, 50),
            'user_id' => null,
            'session_id' => fake()->uuid(),
        ];
    }

    /**
     * Indicate that the query had zero results.
     */
    public function zeroResults(): static
    {
        return $this->state(fn () => [
            'result_count' => 0,
        ]);
    }

    /**
     * Assign an authenticated user to the query.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
        ]);
    }
}
