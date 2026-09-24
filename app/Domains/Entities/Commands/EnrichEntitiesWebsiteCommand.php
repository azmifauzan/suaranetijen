<?php

namespace App\Domains\Entities\Commands;

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\OfficialWebsiteFinder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class EnrichEntitiesWebsiteCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'entities:enrich-websites
                            {--limit=50 : Maximum number of entities to process}
                            {--category= : Filter by category slug or ID}
                            {--force : Re-check entities that already have a website_url}
                            {--delay=200000 : Delay between Wikidata requests in microseconds}';

    /**
     * @var string
     */
    protected $description = 'Look up and enrich official website URLs for active entities from Wikidata';

    public function handle(OfficialWebsiteFinder $finder): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force');
        $delay = max(0, (int) $this->option('delay'));
        $category = $this->option('category');

        $this->info("Scanning entities for official website enrichment (limit: {$limit})...");

        $query = Entity::query()
            ->active()
            ->when(! $force, fn (Builder $q) => $q->whereNull('website_url'));

        if ($category !== null && $category !== '') {
            $query->whereHas('category', function (Builder $q) use ($category) {
                if (is_numeric($category)) {
                    $q->where('id', (int) $category);
                } else {
                    $q->where('slug', $category);
                }
            });
        }

        // Prioritize entities with highest opinion count in 'all' snapshot, then by id asc
        /** @var Collection<int, Entity> $entities */
        $entities = $query->leftJoin('sentiment_snapshots', function ($join) {
            $join->on('entities.id', '=', 'sentiment_snapshots.entity_id')
                ->where('sentiment_snapshots.period', '=', 'all');
        })
            ->select('entities.*')
            ->orderByRaw('COALESCE(sentiment_snapshots.opinion_count, 0) DESC')
            ->orderBy('entities.id', 'asc')
            ->limit($limit)
            ->get();

        if ($entities->isEmpty()) {
            $this->info('No entities need website enrichment.');

            return self::SUCCESS;
        }

        $this->line("Found {$entities->count()} entity candidate(s) to process.");

        $enriched = 0;
        $notFound = 0;

        foreach ($entities as $entity) {
            $url = $finder->find($entity);

            if ($url !== null) {
                $entity->update(['website_url' => $url]);
                Cache::put("enrich:website:{$entity->id}", true, now()->addDays(30));
                $enriched++;
                $this->info("  ✓ [ID: {$entity->id}] {$entity->name} -> {$url}");
            } else {
                Cache::put("enrich:website:{$entity->id}", true, now()->addDays(7));
                $notFound++;
                $this->line("  - [ID: {$entity->id}] {$entity->name} (not found)");
            }

            if ($delay > 0) {
                usleep($delay);
            }
        }

        $this->info("Completed. Enriched {$enriched} of {$entities->count()} entities ({$notFound} not found).");

        return self::SUCCESS;
    }
}
