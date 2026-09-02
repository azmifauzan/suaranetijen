<?php

namespace App\Domains\Sentiment\Models;

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use Carbon\CarbonImmutable;
use Database\Factories\SentimentObservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $entity_id
 * @property int $source_id
 * @property int $source_item_id
 * @property SentimentClass $sentiment
 * @property float|null $model_confidence
 * @property CarbonImmutable $observed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Entity $entity
 * @property-read Source $source
 * @property-read SourceItem $item
 */
#[Fillable([
    'entity_id',
    'source_id',
    'source_item_id',
    'sentiment',
    'model_confidence',
    'observed_at',
])]
class SentimentObservation extends Model
{
    /** @use HasFactory<SentimentObservationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sentiment' => SentimentClass::class,
            'model_confidence' => 'float',
            'observed_at' => 'immutable_datetime',
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
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * @return BelongsTo<SourceItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(SourceItem::class, 'source_item_id');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): SentimentObservationFactory
    {
        return SentimentObservationFactory::new();
    }
}
