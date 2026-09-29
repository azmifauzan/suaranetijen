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

    /**
     * Idempotent: a redelivery after a worker restart must not fail the job (the supervisors run
     * with tries=1), while a real exception still fails it on the first throw.
     */
    public int $tries = 3;

    public int $maxExceptions = 1;

    public function __construct(
        public int $sourceItemId,
        public int $entityId,
        public SentimentClass $sentiment,
        public ?string $matchedTerm = null
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
                'matched_term' => $this->matchedTerm !== null ? mb_substr($this->matchedTerm, 0, 120) : null,
                'model_confidence' => null,
                'observed_at' => $observedAt,
            ]
        );

        $item->update(['processing_state' => ProcessingState::Processed]);
        AggregateDailySentimentJob::dispatch($this->entityId, $observedAt->format('Y-m-d'));
        RefreshSentimentSnapshotJob::dispatch($this->entityId);
    }
}
