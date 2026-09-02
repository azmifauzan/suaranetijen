<?php

namespace App\Domains\Sources\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $source_id
 * @property int|null $source_item_id
 * @property string $payload_ref
 * @property string $payload
 * @property string $content_type
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Source $source
 * @property-read SourceItem|null $item
 */
#[Fillable([
    'source_id',
    'source_item_id',
    'payload_ref',
    'payload',
    'content_type',
    'expires_at',
])]
class RawPayload extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
        ];
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
}
