<?php

namespace App\Domains\Sources\Models;

use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sources\Enums\ProcessingState;
use Carbon\CarbonImmutable;
use Database\Factories\SourceItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $source_id
 * @property int|null $source_document_id
 * @property string $external_id
 * @property string|null $raw_payload_ref
 * @property string $content_hash
 * @property ProcessingState $processing_state
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Source $source
 * @property-read SourceDocument|null $document
 */
#[Fillable([
    'source_id',
    'source_document_id',
    'external_id',
    'raw_payload_ref',
    'content_hash',
    'processing_state',
    'published_at',
    'expires_at',
])]
class SourceItem extends Model
{
    /** @use HasFactory<SourceItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'processing_state' => ProcessingState::class,
            'published_at' => 'immutable_datetime',
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
     * @return BelongsTo<SourceDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(SourceDocument::class, 'source_document_id');
    }

    /**
     * @return HasMany<SentimentObservation, $this>
     */
    public function sentimentObservations(): HasMany
    {
        return $this->hasMany(SentimentObservation::class);
    }

    /**
     * @return HasOne<RawPayload, $this>
     */
    public function rawPayload(): HasOne
    {
        return $this->hasOne(RawPayload::class);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): SourceItemFactory
    {
        return SourceItemFactory::new();
    }
}
