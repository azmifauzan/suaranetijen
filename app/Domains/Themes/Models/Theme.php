<?php

namespace App\Domains\Themes\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $display_label
 * @property string $canonical_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Theme extends Model
{
    protected $table = 'themes';

    protected $fillable = [
        'slug',
        'display_label',
        'canonical_key',
    ];

    /**
     * @return HasMany<ThemeAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(ThemeAlias::class);
    }

    /**
     * @return HasMany<ThemeObservation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(ThemeObservation::class);
    }

    /**
     * @return HasMany<EntityThemeDaily, $this>
     */
    public function dailyAggregates(): HasMany
    {
        return $this->hasMany(EntityThemeDaily::class);
    }

    /**
     * @return HasMany<EntityThemeSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(EntityThemeSnapshot::class);
    }
}
