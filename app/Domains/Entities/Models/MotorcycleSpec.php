<?php

namespace App\Domains\Entities\Models;

use Database\Factories\MotorcycleSpecFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $entity_id
 * @property string|null $body_type
 * @property int|null $engine_cc
 * @property string|null $cooling_system
 * @property string|null $fuel_type
 * @property float|null $power_hp
 * @property float|null $torque_nm
 * @property string|null $transmission
 * @property float|null $fuel_tank_liter
 * @property int|null $weight_kg
 * @property string|null $braking_system
 * @property int|null $release_year
 * @property-read Entity $entity
 */
#[Fillable([
    'entity_id',
    'body_type',
    'engine_cc',
    'cooling_system',
    'fuel_type',
    'power_hp',
    'torque_nm',
    'transmission',
    'fuel_tank_liter',
    'weight_kg',
    'braking_system',
    'release_year',
])]
class MotorcycleSpec extends Model
{
    /** @use HasFactory<MotorcycleSpecFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'engine_cc' => 'integer',
            'power_hp' => 'float',
            'torque_nm' => 'float',
            'fuel_tank_liter' => 'float',
            'weight_kg' => 'integer',
            'release_year' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    protected static function newFactory(): MotorcycleSpecFactory
    {
        return MotorcycleSpecFactory::new();
    }
}
