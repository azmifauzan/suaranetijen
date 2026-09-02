<?php

namespace App\Domains\Themes\Models;

use App\Domains\Entities\Models\Entity;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $entity_id
 * @property int $theme_id
 * @property CarbonImmutable $date
 * @property int $positive_count
 * @property int $neutral_count
 * @property int $negative_count
 * @property int $observation_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $total_observations
 * @property-read int $total_positive
 * @property-read int $total_neutral
 * @property-read int $total_negative
 * @property-read Entity $entity
 * @property-read Theme $theme
 */
class EntityThemeDaily extends Model
{
    protected $table = 'entity_theme_daily';

    protected $fillable = [
        'entity_id',
        'theme_id',
        'date',
        'positive_count',
        'neutral_count',
        'negative_count',
        'observation_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'positive_count' => 'integer',
            'neutral_count' => 'integer',
            'negative_count' => 'integer',
            'observation_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * @return BelongsTo<Theme, $this>
     */
    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }
}
