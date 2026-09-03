<?php

namespace App\Domains\Ratings\Models;

use App\Domains\Entities\Models\Entity;
use Carbon\CarbonImmutable;
use Database\Factories\RatingSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $entity_id
 * @property int $rating_count
 * @property float|null $rating_average
 * @property CarbonImmutable $calculated_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Entity $entity
 */
#[Fillable([
    'entity_id',
    'rating_count',
    'rating_average',
    'calculated_at',
])]
class RatingSnapshot extends Model
{
    /** @use HasFactory<RatingSnapshotFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating_count' => 'integer',
            'rating_average' => 'float',
            'calculated_at' => 'immutable_datetime',
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
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): RatingSnapshotFactory
    {
        return RatingSnapshotFactory::new();
    }
}
