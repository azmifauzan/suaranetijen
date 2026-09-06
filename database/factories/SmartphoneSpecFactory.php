<?php

namespace Database\Factories;

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\SmartphoneSpec;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmartphoneSpec>
 */
class SmartphoneSpecFactory extends Factory
{
    protected $model = SmartphoneSpec::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => Entity::factory(),
            'chipset' => 'Snapdragon 7 Gen 4',
            'ram' => '8/12 GB',
            'storage' => '128/256 GB',
            'screen_size_inch' => 6.7,
            'screen_type' => 'AMOLED 120Hz',
            'rear_camera' => '50 MP + 8 MP + 2 MP',
            'front_camera' => '16 MP',
            'battery_mah' => 5000,
            'fast_charging_watt' => 67,
            'os' => 'Android 15',
            'network' => '5G',
            'release_year' => 2026,
        ];
    }
}
