<?php

namespace Database\Factories;

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\MotorcycleSpec;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MotorcycleSpec>
 */
class MotorcycleSpecFactory extends Factory
{
    protected $model = MotorcycleSpec::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => Entity::factory(),
            'body_type' => 'Sport',
            'engine_cc' => 155,
            'cooling_system' => 'Liquid-cooled',
            'fuel_type' => 'Bensin',
            'power_hp' => 19.0,
            'torque_nm' => 14.7,
            'transmission' => 'Manual 6-percepatan',
            'fuel_tank_liter' => 11.0,
            'weight_kg' => 142,
            'braking_system' => 'Cakram depan-belakang, ABS',
            'release_year' => 2026,
        ];
    }
}
