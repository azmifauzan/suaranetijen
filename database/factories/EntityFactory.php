<?php

namespace Database\Factories;

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Entity>
 */
class EntityFactory extends Factory
{
    protected $model = Entity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'category_id' => Category::factory(),
            'parent_id' => null,
            'type' => EntityType::Brand,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'description' => fake()->sentence(),
            'status' => EntityStatus::Active,
            'searchable' => true,
            'rankable' => true,
        ];
    }

    /**
     * Indicate that the entity is a product.
     */
    public function product(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EntityType::Product,
        ]);
    }

    /**
     * Indicate that the entity is a service.
     */
    public function service(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => EntityType::Service,
        ]);
    }

    /**
     * Indicate that the entity is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EntityStatus::Disabled,
        ]);
    }

    /**
     * Indicate that the entity is not searchable.
     */
    public function unsearchable(): static
    {
        return $this->state(fn (array $attributes) => [
            'searchable' => false,
        ]);
    }
}
