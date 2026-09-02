<?php

namespace App\Domains\Sentiment\Jobs;

use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sources\Enums\ProcessingState;
use App\Domains\Sources\Models\SourceItem;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpsertSentimentObservationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $sourceItemId,
        public int $entityId,
        public SentimentClass $sentiment
    ) {
        $this->queue = 'analysis';
    }

    public function handle(): void
    {
        $item = SourceItem::find($this->sourceItemId);
        if ($item === null) {
            return;
        }

        $observedAt = $item->published_at ?? CarbonImmutable::now();
        SentimentObservation::updateOrCreate(
            [
                'entity_id' => $this->entityId,
                'source_item_id' => $item->id,
            ],
            [
                'source_id' => $item->source_id,
                'sentiment' => $this->sentiment,
                'model_confidence' => null,
                'observed_at' => $observedAt,
            ]
        );

        $item->update(['processing_state' => ProcessingState::Processed]);
        AggregateDailySentimentJob::dispatch($this->entityId, $observedAt->format('Y-m-d'));
        RefreshSentimentSnapshotJob::dispatch($this->entityId);
    }
}
