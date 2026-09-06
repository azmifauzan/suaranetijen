<?php

namespace App\Domains\Entities\Models;

use Database\Factories\SmartphoneSpecFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $entity_id
 * @property string|null $chipset
 * @property string|null $ram
 * @property string|null $storage
 * @property float|null $screen_size_inch
 * @property string|null $screen_type
 * @property string|null $rear_camera
 * @property string|null $front_camera
 * @property int|null $battery_mah
 * @property int|null $fast_charging_watt
 * @property string|null $os
 * @property string|null $network
 * @property int|null $release_year
 * @property-read Entity $entity
 */
#[Fillable([
    'entity_id',
    'chipset',
    'ram',
    'storage',
    'screen_size_inch',
    'screen_type',
    'rear_camera',
    'front_camera',
    'battery_mah',
    'fast_charging_watt',
    'os',
    'network',
    'release_year',
])]
class SmartphoneSpec extends Model
{
    /** @use HasFactory<SmartphoneSpecFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'screen_size_inch' => 'float',
            'battery_mah' => 'integer',
            'fast_charging_watt' => 'integer',
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

    protected static function newFactory(): SmartphoneSpecFactory
    {
        return SmartphoneSpecFactory::new();
    }
}
