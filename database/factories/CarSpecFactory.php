<?php

namespace Database\Factories;

use App\Domains\Entities\Models\CarSpec;
use App\Domains\Entities\Models\Entity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CarSpec>
 */
class CarSpecFactory extends Factory
{
    protected $model = CarSpec::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => Entity::factory(),
            'body_type' => 'SUV',
            'engine_cc' => 1998,
            'cylinder_count' => 4,
            'fuel_type' => 'Bensin',
            'power_hp' => 163.0,
            'torque_nm' => 360.0,
            'transmission' => 'AT 6-percepatan',
            'drivetrain' => 'FWD',
            'fuel_tank_liter' => 65,
            'seating_capacity' => 5,
            'dimensions_mm' => '4673 x 1849 x 1756',
            'release_year' => 2026,
        ];
    }
}
