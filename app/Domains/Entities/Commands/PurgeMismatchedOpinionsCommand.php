<?php

namespace App\Domains\Entities\Commands;

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\AliasPolicy;
use App\Domains\Entities\Services\EntityAggregateRebuilder;
use App\Domains\Entities\Services\EntityMatcher;
use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sources\Models\RawPayload;
use App\Domains\Sources\Models\Source;
use App\Domains\Themes\Models\ThemeObservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeMismatchedOpinionsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'entities:purge-mismatched-opinions
        {--entity=* : Entity slugs to clean (default: every entity with a blocked or uppercase-only alias)}
        {--purge-unverifiable=* : Entity slugs whose opinions with an expired raw payload are deleted too}
        {--source=* : Only look at opinions from these source keys (default: every source)}
        {--dry-run : Count what would be deleted without changing anything}';

    /**
     * @var string
     */
    protected $description = 'Delete opinions the current EntityMatcher would no longer attribute to their entity, then rebuild that entity\'s sentiment and theme aggregates';

    public function handle(EntityMatcher $matcher, EntityAggregateRebuilder $rebuilder): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $unverifiableSlugs = (array) $this->option('purge-unverifiable');
        $sourceIds = $this->option('source') === []
            ? null
            : Source::query()->whereIn('key', (array) $this->option('source'))->pluck('id')->all();

        $entities = Entity::query()
            ->when(
                $this->option('entity') !== [],
                fn ($query) => $query->whereIn('slug', (array) $this->option('entity')),
                fn ($query) => $query->whereHas('aliases', fn ($aliases) => $aliases->whereIn('normalized_alias', $this->riskyAliases()))
            )
            ->get(['id', 'slug', 'name']);

        foreach ($entities as $entity) {
            $mismatched = [];
            $unverifiable = 0;
            $verified = 0;

            SentimentObservation::query()->where('entity_id', $entity->id)
                ->when($sourceIds !== null, fn ($query) => $query->whereIn('source_id', $sourceIds))
                ->orderBy('id')
                ->chunkById(500, function ($observations) use ($matcher, $entity, $unverifiableSlugs, &$mismatched, &$unverifiable, &$verified): void {
                    $payloads = RawPayload::query()
                        ->whereIn('source_item_id', $observations->pluck('source_item_id'))
                        ->orderBy('id')
                        ->pluck('payload', 'source_item_id');

                    foreach ($observations as $observation) {
                        $payload = $payloads->get($observation->source_item_id);

                        if (! is_string($payload) || $payload === '') {
                            $unverifiable++;
                            if (in_array($entity->slug, $unverifiableSlugs, true)) {
                                $mismatched[] = $observation->source_item_id;
                            }

                            continue;
                        }

                        $verified++;
                        if ($matcher->match($payload)?->id !== $entity->id) {
                            $mismatched[] = $observation->source_item_id;
                        }
                    }
                });

            $this->line("{$entity->name}: verified {$verified}, unverifiable {$unverifiable}, to delete ".count($mismatched));

            if ($dryRun || $mismatched === []) {
                continue;
            }

            $this->purge($entity, $mismatched, $rebuilder);
        }

        $this->info($dryRun ? 'Dry run, nothing changed.' : 'Done. Run themes:summarize to regenerate summaries.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function riskyAliases(): array
    {
        return DB::table('entity_aliases')
            ->pluck('normalized_alias')
            ->map(fn (mixed $alias): string => (string) $alias)
            ->unique()
            ->filter(fn (string $alias): bool => ! AliasPolicy::isUsable($alias) || AliasPolicy::requiresUppercase($alias))
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $sourceItemIds
     */
    private function purge(Entity $entity, array $sourceItemIds, EntityAggregateRebuilder $rebuilder): void
    {
        foreach (array_chunk($sourceItemIds, 500) as $chunk) {
            SentimentObservation::query()->where('entity_id', $entity->id)->whereIn('source_item_id', $chunk)->delete();
            ThemeObservation::query()->where('entity_id', $entity->id)->whereIn('source_item_id', $chunk)->delete();
        }

        $rebuilder->rebuild($entity->id);

        $this->info('Purged '.count($sourceItemIds)." opinion(s) for {$entity->name} and rebuilt its aggregates.");
    }
}
