<?php

namespace App\Domains\Themes\Models;

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $entity_id
 * @property int $theme_id
 * @property Period $window
 * @property int $observation_count
 * @property int $positive_count
 * @property int $neutral_count
 * @property int $negative_count
 * @property int $rank
 * @property Carbon $calculated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Entity $entity
 * @property-read Theme $theme
 */
class EntityThemeSnapshot extends Model
{
    protected $table = 'entity_theme_snapshots';

    protected $fillable = [
        'entity_id',
        'theme_id',
        'window',
        'observation_count',
        'positive_count',
        'neutral_count',
        'negative_count',
        'rank',
        'calculated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'window' => Period::class,
            'observation_count' => 'integer',
            'positive_count' => 'integer',
            'neutral_count' => 'integer',
            'negative_count' => 'integer',
            'rank' => 'integer',
            'calculated_at' => 'datetime',
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
