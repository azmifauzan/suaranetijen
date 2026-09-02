<?php

namespace App\Domains\Sentiment\Models;

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use Carbon\CarbonImmutable;
use Database\Factories\SentimentSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $entity_id
 * @property Period $period
 * @property int $positive_count
 * @property int $neutral_count
 * @property int $negative_count
 * @property int $opinion_count
 * @property float|null $score
 * @property string $sentiment_model_version
 * @property string $score_formula_version
 * @property CarbonImmutable $calculated_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Entity $entity
 */
#[Fillable([
    'entity_id',
    'period',
    'positive_count',
    'neutral_count',
    'negative_count',
    'opinion_count',
    'score',
    'sentiment_model_version',
    'score_formula_version',
    'calculated_at',
])]
class SentimentSnapshot extends Model
{
    /** @use HasFactory<SentimentSnapshotFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => Period::class,
            'positive_count' => 'integer',
            'neutral_count' => 'integer',
            'negative_count' => 'integer',
            'opinion_count' => 'integer',
            'score' => 'float',
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
    protected static function newFactory(): SentimentSnapshotFactory
    {
        return SentimentSnapshotFactory::new();
    }
}
