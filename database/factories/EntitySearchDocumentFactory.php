<?php

namespace Database\Factories;

use App\Domains\Entities\Models\Entity;
use App\Domains\Search\Models\EntitySearchDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntitySearchDocument>
 */
class EntitySearchDocumentFactory extends Factory
{
    protected $model = EntitySearchDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => Entity::factory(),
            'description_text' => fake()->paragraph(),
            'theme_text' => 'harga murah kualitas terjamin',
            'spec_text' => 'snapdragon 12gb 256gb 5000mah',
            'summary_text' => 'Netizen menyukai performa dan daya tahan baterai.',
        ];
    }
}
