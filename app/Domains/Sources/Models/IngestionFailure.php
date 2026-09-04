<?php

namespace App\Domains\Sources\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

/**
 * @property int $id
 * @property int $source_id
 * @property int|null $source_document_id
 * @property int|null $source_item_id
 * @property string $stage
 * @property string $error_message
 * @property string|null $exception_class
 * @property array<string, mixed>|null $context
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Source $source
 * @property-read SourceDocument|null $document
 * @property-read SourceItem|null $item
 */
#[Fillable([
    'source_id',
    'source_document_id',
    'source_item_id',
    'stage',
    'error_message',
    'exception_class',
    'context',
    'resolved_at',
])]
class IngestionFailure extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'resolved_at' => 'immutable_datetime',
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
     * @return BelongsTo<SourceItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(SourceItem::class, 'source_item_id');
    }

    /**
     * Helper to record a failure from caught Throwable or error message.
     *
     * @param  array<string, mixed>  $context
     */
    public static function record(
        int $sourceId,
        string $stage,
        Throwable|string $error,
        ?int $documentId = null,
        ?int $itemId = null,
        array $context = []
    ): self {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;
        $exceptionClass = $error instanceof Throwable ? get_class($error) : null;

        return self::create([
            'source_id' => $sourceId,
            'source_document_id' => $documentId,
            'source_item_id' => $itemId,
            'stage' => $stage,
            'error_message' => $message,
            'exception_class' => $exceptionClass,
            'context' => $context,
        ]);
    }
}
