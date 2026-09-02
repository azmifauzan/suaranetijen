<?php

namespace App\Domains\Sentiment\Models;

use App\Domains\Entities\Models\Entity;
use Carbon\CarbonImmutable;
use Database\Factories\SentimentDailyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $entity_id
 * @property CarbonImmutable $date
 * @property int $positive_count
 * @property int $neutral_count
 * @property int $negative_count
 * @property int $opinion_count
 * @property float|null $score
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Entity $entity
 */
#[Fillable([
    'entity_id',
    'date',
    'positive_count',
    'neutral_count',
    'negative_count',
    'opinion_count',
    'score',
])]
class SentimentDaily extends Model
{
    /** @use HasFactory<SentimentDailyFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sentiment_daily';

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
            'opinion_count' => 'integer',
            'score' => 'float',
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
    protected static function newFactory(): SentimentDailyFactory
    {
        return SentimentDailyFactory::new();
    }
}
