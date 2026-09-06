<?php

namespace App\Domains\Entities\Models;

use Database\Factories\CarSpecFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $entity_id
 * @property string|null $body_type
 * @property int|null $engine_cc
 * @property int|null $cylinder_count
 * @property string|null $fuel_type
 * @property float|null $power_hp
 * @property float|null $torque_nm
 * @property string|null $transmission
 * @property string|null $drivetrain
 * @property int|null $fuel_tank_liter
 * @property int|null $seating_capacity
 * @property string|null $dimensions_mm
 * @property int|null $release_year
 * @property-read Entity $entity
 */
#[Fillable([
    'entity_id',
    'body_type',
    'engine_cc',
    'cylinder_count',
    'fuel_type',
    'power_hp',
    'torque_nm',
    'transmission',
    'drivetrain',
    'fuel_tank_liter',
    'seating_capacity',
    'dimensions_mm',
    'release_year',
])]
class CarSpec extends Model
{
    /** @use HasFactory<CarSpecFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'engine_cc' => 'integer',
            'cylinder_count' => 'integer',
            'power_hp' => 'float',
            'torque_nm' => 'float',
            'fuel_tank_liter' => 'integer',
            'seating_capacity' => 'integer',
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

    protected static function newFactory(): CarSpecFactory
    {
        return CarSpecFactory::new();
    }
}
