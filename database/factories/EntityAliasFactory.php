<?php

namespace Database\Factories;

use App\Domains\Entities\Enums\AliasType;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Entities\Services\TextNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntityAlias>
 */
class EntityAliasFactory extends Factory
{
    protected $model = EntityAlias::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $alias = fake()->word().' '.fake()->word().' '.fake()->unique()->randomNumber(4);

        return [
            'entity_id' => Entity::factory(),
            'alias' => $alias,
            'normalized_alias' => TextNormalizer::normalize($alias),
            'alias_type' => AliasType::CommonVariant,
        ];
    }
}
