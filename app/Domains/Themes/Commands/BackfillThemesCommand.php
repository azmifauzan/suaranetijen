<?php

namespace App\Domains\Themes\Commands;

use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sources\Models\RawPayload;
use App\Domains\Themes\Jobs\ExtractThemesJob;
use Illuminate\Console\Command;

class BackfillThemesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'themes:backfill';

    /**
     * @var string
     */
    protected $description = 'Dispatch theme extraction for existing sentiment observations whose raw payload has not expired yet (one-time catch-up for the period before ClassifySentimentJob dispatched ExtractThemesJob)';

    public function handle(): int
    {
        $dispatched = 0;
        $skipped = 0;

        SentimentObservation::query()
            ->orderBy('id')
            ->chunkById(500, function ($observations) use (&$dispatched, &$skipped): void {
                foreach ($observations as $observation) {
                    $payload = RawPayload::query()
                        ->where('source_item_id', $observation->source_item_id)
                        ->latest('id')
                        ->value('payload');

                    if (! is_string($payload) || $payload === '') {
                        $skipped++;

                        continue;
                    }

                    ExtractThemesJob::dispatch(
                        entityId: $observation->entity_id,
                        sourceId: $observation->source_id,
                        sourceItemId: $observation->source_item_id,
                        text: $payload,
                        sourceDocumentHash: $observation->item->content_hash ?? null,
                        contextSentiment: $observation->sentiment,
                        publishedAt: $observation->observed_at
                    );
                    $dispatched++;
                }
            });

        $this->info("Dispatched theme extraction for {$dispatched} observation(s), skipped {$skipped} with expired raw payload.");

        return self::SUCCESS;
    }
}
