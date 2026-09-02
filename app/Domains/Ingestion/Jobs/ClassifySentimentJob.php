<?php

namespace App\Domains\Ingestion\Jobs;

use App\Domains\Sentiment\Jobs\UpsertSentimentObservationJob;
use App\Domains\Sentiment\Services\SentimentClassifier;
use App\Domains\Sources\Enums\ProcessingState;
use App\Domains\Sources\Models\RawPayload;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Models\UnmatchedMention;
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
    }
}
