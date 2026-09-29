<?php

namespace App\Domains\Ingestion\Jobs;

use App\Domains\Entities\Jobs\EnrichEntityWebsiteJob;
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
use Illuminate\Support\Facades\Cache;

class ClassifySentimentJob implements ShouldQueue
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
        public ?string $matchedTerm = null
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

        UpsertSentimentObservationJob::dispatch($item->id, $this->entityId, $sentiment, $this->matchedTerm);

        // Theme extraction is a second, independent branch off the same relevant-opinion
        // output (docs/25) — it never blocks, and is never blocked by, sentiment classification.
        // The LLM extractor is batched by themes:extract-pending instead of one call per opinion.
        if (config('themes.extractor') !== 'llm') {
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

        if (Cache::add("enrich:website:{$this->entityId}", true, now()->addDays(7))) {
            EnrichEntityWebsiteJob::dispatch($this->entityId);
        }
    }
}
