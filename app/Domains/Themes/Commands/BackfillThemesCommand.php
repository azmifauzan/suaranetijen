<?php

namespace App\Domains\Themes\Commands;

use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sources\Models\RawPayload;
use App\Domains\Themes\Jobs\ExtractThemesJob;
use App\Domains\Themes\Models\ThemeObservation;
use Illuminate\Console\Command;

class BackfillThemesCommand extends Command
{
    private const MAX_TEXT_CHARS = 4000;

    /**
     * @var string
     */
    protected $signature = 'themes:backfill
        {--per-minute= : Pace LLM dispatch to this many jobs per minute (default: 80% of themes.llm_per_minute)}
        {--dry-run : Count what would be dispatched without dispatching or calling the LLM}';

    /**
     * @var string
     */
    protected $description = 'Rebuild theme observations from existing sentiment observations whose raw payload still exists. With THEMES_EXTRACTOR=llm, items already extracted or too short are skipped and dispatch is paced so the shared LLM limiter is never flooded';

    public function handle(): int
    {
        $useLlm = config('themes.extractor') === 'llm';
        $minChars = (int) config('themes.llm_min_chars', 30);
        $perMinute = max(1, (int) ($this->option('per-minute') ?: floor((int) config('themes.llm_per_minute', 60) * 0.8)));
        $dryRun = (bool) $this->option('dry-run');

        $dispatched = 0;
        $skipped = 0;

        SentimentObservation::query()
            ->with('item:id,content_hash')
            ->orderBy('id')
            ->chunkById(500, function ($observations) use (&$dispatched, &$skipped, $useLlm, $minChars, $perMinute, $dryRun): void {
                $itemIds = $observations->pluck('source_item_id');

                // Latest payload per item (ascending id, so keyBy keeps the newest).
                $texts = RawPayload::query()
                    ->whereIn('source_item_id', $itemIds)
                    ->orderBy('id')
                    ->selectRaw('source_item_id, substr(payload, 1, '.self::MAX_TEXT_CHARS.') as text')
                    ->get()
                    ->keyBy('source_item_id');

                $alreadyExtracted = $useLlm
                    ? ThemeObservation::query()->where('extractor', 'llm')->whereIn('source_item_id', $itemIds)->pluck('source_item_id')->flip()
                    : collect();

                foreach ($observations as $observation) {
                    $text = $texts->get($observation->source_item_id)?->getAttribute('text');

                    if (! is_string($text) || $text === ''
                        || ($useLlm && (mb_strlen(trim($text)) < $minChars || $alreadyExtracted->has($observation->source_item_id)))) {
                        $skipped++;

                        continue;
                    }

                    if (! $dryRun) {
                        $pending = ExtractThemesJob::dispatch(
                            entityId: $observation->entity_id,
                            sourceId: $observation->source_id,
                            sourceItemId: $observation->source_item_id,
                            text: $text,
                            sourceDocumentHash: $observation->item->content_hash ?? null,
                            contextSentiment: $observation->sentiment,
                            publishedAt: $observation->observed_at
                        );

                        if ($useLlm) {
                            $pending->delay(now()->addSeconds(intdiv($dispatched * 60, $perMinute)));
                        }
                    }
                    $dispatched++;
                }
            });

        $verb = $dryRun ? 'Would dispatch' : 'Dispatched';
        $eta = $useLlm ? ' (~'.(int) ceil($dispatched / $perMinute)." min at {$perMinute}/min)" : '';
        $this->info("{$verb} theme extraction for {$dispatched} observation(s){$eta}, skipped {$skipped} (no payload, too short, or already extracted).");

        return self::SUCCESS;
    }
}
