<?php

namespace Database\Factories;

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\PersonProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonProfile>
 */
class PersonProfileFactory extends Factory
{
    protected $model = PersonProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => Entity::factory(),
            'birth_date' => '1980-01-01',
            'birth_place' => 'Jakarta',
            'occupation' => 'Politisi',
            'affiliation' => 'Partai Contoh',
            'active_since_year' => 2010,
            'official_website' => 'https://example.id',
        ];
    }
}
