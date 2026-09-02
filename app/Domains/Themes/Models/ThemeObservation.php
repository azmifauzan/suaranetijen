<?php

namespace App\Domains\Themes\Models;

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $entity_id
 * @property int $theme_id
 * @property int $source_id
 * @property int|null $source_item_id
 * @property string|null $source_document_hash
 * @property SentimentClass $sentiment
 * @property float|null $confidence
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $count
 * @property-read int $total_observations
 * @property-read int $total_positive
 * @property-read int $total_neutral
 * @property-read int $total_negative
 * @property-read Entity $entity
 * @property-read Theme $theme
 * @property-read Source $source
 * @property-read SourceItem|null $sourceItem
 */
class ThemeObservation extends Model
{
    protected $table = 'theme_observations';

    protected $fillable = [
        'entity_id',
        'theme_id',
        'source_id',
        'source_item_id',
        'source_document_hash',
        'sentiment',
        'confidence',
        'published_at',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sentiment' => SentimentClass::class,
            'confidence' => 'float',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
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
    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(SourceItem::class);
    }
}
