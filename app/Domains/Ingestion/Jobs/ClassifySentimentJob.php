<?php

namespace App\Domains\Ingestion\Jobs;

use App\Domains\Sentiment\Jobs\UpsertSentimentObservationJob;
use App\Domains\Sentiment\Services\SentimentClassifier;
use App\Domains\Sources\Enums\ProcessingState;
use App\Domains\Sources\Models\IngestionFailure;
use App\Domains\Sources\Models\RawPayload;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Models\UnmatchedMention;
use App\Domains\Themes\Jobs\ExtractThemesJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ClassifySentimentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $sourceItemId,
        public int $entityId
    ) {
        $this->queue = 'analysis';
    }

    public function handle(SentimentClassifier $classifier): void
    {
        $item = SourceItem::find($this->sourceItemId);
        if ($item === null) {
            return;
        }

        $payload = RawPayload::query()
            ->where('source_item_id', $item->id)
            ->latest('id')
            ->value('payload');
        if (! is_string($payload) || $payload === '') {
            $item->update(['processing_state' => ProcessingState::Failed]);
            IngestionFailure::record(
                $item->source_id,
                'classify',
                'Raw payload missing or expired for source item',
                $item->source_document_id,
                $item->id
            );

            return;
        }

        $sentiment = $classifier->classify($payload);
        if ($sentiment === null) {
            UnmatchedMention::updateOrCreate(
                ['source_item_id' => $item->id],
                [
                    'source_id' => $item->source_id,
                    'content_hash' => $item->content_hash,
                    'reason' => 'not_an_evaluation',
                ]
            );
            $item->update(['processing_state' => ProcessingState::Skipped]);

            return;
        }

        UpsertSentimentObservationJob::dispatch($item->id, $this->entityId, $sentiment);

        // Theme extraction is a second, independent branch off the same relevant-opinion
        // output (docs/25) — it never blocks, and is never blocked by, sentiment classification.
        ExtractThemesJob::dispatch(
            entityId: $this->entityId,
            sourceId: $item->source_id,
            sourceItemId: $item->id,
            text: $payload,
            sourceDocumentHash: $item->content_hash,
            contextSentiment: $sentiment,
            publishedAt: $item->published_at
        );
    }
}
