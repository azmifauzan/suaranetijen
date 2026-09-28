<?php

namespace App\Domains\Themes\Commands;

use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sources\Models\RawPayload;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Themes\Jobs\ExtractThemesBatchJob;
use App\Domains\Themes\Models\ThemeObservation;
use Illuminate\Console\Command;

class BackfillLlmThemesCommand extends Command
{
    private const MAX_TEXT_CHARS = 4000;

    /**
     * @var string
     */
    protected $signature = 'themes:backfill-batch
        {--entity-min-opinions= : Only entities with at least this many opinions (default: config(scoring.public_min_opinions), i.e. entities that already clear the public score threshold)}
        {--entity-cap= : Most-recent opinions considered per entity (default: config(themes.backfill_entity_cap))}
        {--batch-size= : Opinions sent to the LLM per call (default: config(themes.llm_batch_size))}
        {--per-minute= : Pace batch dispatch to this many jobs per minute (default: 80% of themes.llm_per_minute)}
        {--dry-run : Count what would be dispatched without dispatching or calling the LLM}';

    /**
     * @var string
     */
    protected $description = 'Rebuild theme observations for publicly-eligible entities via batched LLM calls (many opinions per call), instead of one call per opinion — see themes:backfill for the per-opinion version';

    public function handle(): int
    {
        if (config('themes.extractor') !== 'llm') {
            $this->error('THEMES_EXTRACTOR is not llm; nothing to batch-backfill.');

            return self::FAILURE;
        }

        $minOpinions = (int) ($this->option('entity-min-opinions') ?: config('scoring.public_min_opinions', 30));
        $entityCap = (int) ($this->option('entity-cap') ?: config('themes.backfill_entity_cap', 150));
        $batchSize = max(1, (int) ($this->option('batch-size') ?: config('themes.llm_batch_size', 15)));
        $perMinute = max(1, (int) ($this->option('per-minute') ?: floor((int) config('themes.llm_per_minute', 60) * 0.8)));
        $minChars = (int) config('themes.llm_min_chars', 30);
        $dryRun = (bool) $this->option('dry-run');

        $entityIds = SentimentObservation::query()
            ->select('entity_id')
            ->groupBy('entity_id')
            ->havingRaw('count(*) >= ?', [$minOpinions])
            ->pluck('entity_id');

        $batchesDispatched = 0;
        $itemsDispatched = 0;
        $itemsSkipped = 0;

        foreach ($entityIds as $entityId) {
            $observations = SentimentObservation::query()
                ->where('entity_id', $entityId)
                ->orderByDesc('observed_at')
                ->limit($entityCap)
                ->get(['source_id', 'source_item_id', 'observed_at']);

            $itemIds = $observations->pluck('source_item_id');

            $texts = RawPayload::query()
                ->whereIn('source_item_id', $itemIds)
                ->orderBy('id')
                ->selectRaw('source_item_id, substr(payload, 1, '.self::MAX_TEXT_CHARS.') as text')
                ->get()
                ->keyBy('source_item_id');

            $hashes = SourceItem::query()->whereIn('id', $itemIds)->pluck('content_hash', 'id');

            $alreadyExtracted = ThemeObservation::query()
                ->where('extractor', 'llm')
                ->whereIn('source_item_id', $itemIds)
                ->pluck('source_item_id')
                ->flip();

            $items = [];
            foreach ($observations as $observation) {
                $text = $texts->get($observation->source_item_id)?->getAttribute('text');

                if (! is_string($text) || mb_strlen(trim($text)) < $minChars
                    || $alreadyExtracted->has($observation->source_item_id)) {
                    $itemsSkipped++;

                    continue;
                }

                $items[] = [
                    'sourceItemId' => $observation->source_item_id,
                    'sourceId' => $observation->source_id,
                    'sourceDocumentHash' => $hashes->get($observation->source_item_id),
                    'publishedAt' => $observation->observed_at->toIso8601String(),
                    'text' => $text,
                ];
            }

            foreach (array_chunk($items, $batchSize) as $chunk) {
                if (! $dryRun) {
                    ExtractThemesBatchJob::dispatch($entityId, $chunk)
                        ->delay(now()->addSeconds(intdiv($batchesDispatched * 60, $perMinute)));
                }

                $batchesDispatched++;
                $itemsDispatched += count($chunk);
            }
        }

        $verb = $dryRun ? 'Would dispatch' : 'Dispatched';
        $eta = $batchesDispatched > 0 ? ' (~'.(int) ceil($batchesDispatched / $perMinute)." min at {$perMinute} batches/min)" : '';
        $this->info(
            "{$verb} {$batchesDispatched} batch job(s) covering {$itemsDispatched} opinion(s) across "
            ."{$entityIds->count()} entit(y/ies){$eta}, skipped {$itemsSkipped} (no payload, too short, or already extracted)."
        );

        return self::SUCCESS;
    }
}
