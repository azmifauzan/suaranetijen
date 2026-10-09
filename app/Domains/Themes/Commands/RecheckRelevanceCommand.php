<?php

namespace App\Domains\Themes\Commands;

use App\Domains\Entities\Models\Entity;
use App\Domains\Themes\Jobs\RecheckRelevanceBatchJob;
use App\Domains\Themes\Models\ThemeObservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecheckRelevanceCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'themes:recheck-relevance
        {--entity=* : Entity slug(s) to recheck}
        {--ambiguous : Recheck every non-person entity whose name or an alias is a single word}
        {--per-minute= : Pace batch dispatch (default: 80% of themes.llm_per_minute)}
        {--dry-run : Count what would be sent without calling the LLM}';

    /**
     * @var string
     */
    protected $description = 'Ask the LLM whether stored opinions are really about their entity, from the theme contexts, and delete the ones that are not';

    public function handle(): int
    {
        $entities = $this->targetEntities();
        if ($entities === []) {
            $this->error('Give --entity=<slug> or --ambiguous.');

            return self::FAILURE;
        }

        $batchSize = max(1, (int) config('themes.llm_batch_size', 15));
        $perMinute = max(1, (int) ($this->option('per-minute') ?: floor((int) config('themes.llm_per_minute', 60) * 0.8)));
        $batches = 0;
        $opinions = 0;

        foreach ($entities as $entity) {
            $items = ThemeObservation::query()
                ->where('entity_id', $entity->id)
                ->where('extractor', 'llm')
                ->whereNotNull('source_item_id')
                ->where('context', '<>', '')
                ->get(['source_item_id', 'context'])
                ->groupBy('source_item_id')
                ->map(fn ($rows, $sourceItemId) => ['sourceItemId' => (int) $sourceItemId, 'text' => $rows->pluck('context')->implode(' ')])
                ->values()
                ->all();

            foreach (array_chunk($items, $batchSize) as $chunk) {
                if (! $this->option('dry-run')) {
                    RecheckRelevanceBatchJob::dispatch($entity->id, $chunk)->delay(now()->addSeconds(intdiv($batches * 60, $perMinute)));
                }

                $batches++;
                $opinions += count($chunk);
            }
        }

        $this->info(($this->option('dry-run') ? 'Would dispatch ' : 'Dispatched ')."{$batches} batch job(s) covering {$opinions} opinion(s) across ".count($entities).' entity(ies).');

        return self::SUCCESS;
    }

    /**
     * @return array<int, Entity>
     */
    private function targetEntities(): array
    {
        $query = Entity::query()->where('type', '<>', 'person');

        if ($this->option('ambiguous')) {
            $singleWordAliases = DB::table('entity_aliases')->whereRaw("normalized_alias !~ '\\s'")->pluck('entity_id');
            $query->where(fn ($q) => $q->whereIn('id', $singleWordAliases)->orWhereRaw("name !~ '\\s'"));
        } elseif ($this->option('entity') !== []) {
            $query->whereIn('slug', (array) $this->option('entity'));
        } else {
            return [];
        }

        return $query->get()->values()->all();
    }
}
