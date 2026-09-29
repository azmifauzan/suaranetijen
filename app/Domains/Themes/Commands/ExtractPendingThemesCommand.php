<?php

namespace App\Domains\Themes\Commands;

use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sources\Models\RawPayload;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Themes\Jobs\ExtractThemesBatchJob;
use Illuminate\Console\Command;

class ExtractPendingThemesCommand extends Command
{
    private const MAX_TEXT_CHARS = 4000;

    /**
     * @var string
     */
    protected $signature = 'themes:extract-pending
        {--per-minute= : Pace batch dispatch to this many jobs per minute (default: 80% of themes.llm_per_minute)}
        {--dry-run : Count what would be dispatched without dispatching or calling the LLM}';

    /**
     * @var string
     */
    protected $description = 'Send opinions that are not yet theme-extracted to the LLM in batches, one call per themes.llm_batch_size opinions of the same entity';

    public function handle(): int
    {
        if (config('themes.extractor') !== 'llm') {
            $this->error('THEMES_EXTRACTOR is not llm; nothing to extract.');

            return self::FAILURE;
        }

        $batchSize = max(1, (int) config('themes.llm_batch_size', 15));
        $perMinute = max(1, (int) ($this->option('per-minute') ?: floor((int) config('themes.llm_per_minute', 60) * 0.8)));
        $minChars = (int) config('themes.llm_min_chars', 30);
        $dryRun = (bool) $this->option('dry-run');

        // Raw payloads expire (72h adapter TTL); older pending rows can no longer be extracted.
        $pending = SentimentObservation::query()
            ->whereNull('themes_extracted_at')
            ->where('created_at', '>=', now()->subHours((int) config('themes.pending_window_hours', 60)))
            ->get(['id', 'entity_id', 'source_id', 'source_item_id', 'observed_at']);

        $itemIds = $pending->pluck('source_item_id');

        $texts = RawPayload::query()
            ->whereIn('source_item_id', $itemIds)
            ->orderBy('id')
            ->selectRaw('source_item_id, substr(payload, 1, '.self::MAX_TEXT_CHARS.') as text')
            ->get()
            ->keyBy('source_item_id');

        $hashes = SourceItem::query()->whereIn('id', $itemIds)->pluck('content_hash', 'id');

        $batches = 0;
        $items = 0;
        $skippedIds = [];

        foreach ($pending->groupBy('entity_id') as $entityId => $observations) {
            $payloadItems = [];

            foreach ($observations as $observation) {
                $text = $texts->get($observation->source_item_id)?->getAttribute('text');

                if (! is_string($text) || mb_strlen(trim($text)) < $minChars) {
                    $skippedIds[] = $observation->id;

                    continue;
                }

                $payloadItems[] = [
                    'sourceItemId' => $observation->source_item_id,
                    'sourceId' => $observation->source_id,
                    'sourceDocumentHash' => $hashes->get($observation->source_item_id),
                    'publishedAt' => $observation->observed_at->toIso8601String(),
                    'text' => $text,
                ];
            }

            foreach (array_chunk($payloadItems, $batchSize) as $chunk) {
                if (! $dryRun) {
                    ExtractThemesBatchJob::dispatch((int) $entityId, $chunk)
                        ->delay(now()->addSeconds(intdiv($batches * 60, $perMinute)));
                }

                $batches++;
                $items += count($chunk);
            }
        }

        if (! $dryRun && $skippedIds !== []) {
            SentimentObservation::query()->whereIn('id', $skippedIds)->update(['themes_extracted_at' => now()]);
        }

        $verb = $dryRun ? 'Would dispatch' : 'Dispatched';
        $this->info("{$verb} {$batches} batch job(s) covering {$items} opinion(s), skipped ".count($skippedIds).' (no payload or too short).');

        return self::SUCCESS;
    }
}
