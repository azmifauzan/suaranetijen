<?php

namespace App\Domains\Entities\Commands;

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\AliasPolicy;
use App\Domains\Entities\Services\EntityMatcher;
use App\Domains\Sentiment\Models\SentimentDaily;
use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sentiment\Services\SentimentAggregator;
use App\Domains\Sources\Models\RawPayload;
use App\Domains\Themes\Models\EntityThemeDaily;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\EntityThemeSummary;
use App\Domains\Themes\Models\ThemeObservation;
use App\Domains\Themes\Services\ThemeAggregator;
use Carbon\CarbonImmutable;
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
        {--dry-run : Count what would be deleted without changing anything}';

    /**
     * @var string
     */
    protected $description = 'Delete opinions the current EntityMatcher would no longer attribute to their entity, then rebuild that entity\'s sentiment and theme aggregates';

    public function handle(EntityMatcher $matcher, SentimentAggregator $sentiment, ThemeAggregator $themes): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $unverifiableSlugs = (array) $this->option('purge-unverifiable');

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

            SentimentObservation::query()->where('entity_id', $entity->id)->orderBy('id')
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

            $this->purge($entity, $mismatched, $sentiment, $themes);
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
    private function purge(Entity $entity, array $sourceItemIds, SentimentAggregator $sentiment, ThemeAggregator $themes): void
    {
        foreach (array_chunk($sourceItemIds, 500) as $chunk) {
            SentimentObservation::query()->where('entity_id', $entity->id)->whereIn('source_item_id', $chunk)->delete();
            ThemeObservation::query()->where('entity_id', $entity->id)->whereIn('source_item_id', $chunk)->delete();
        }

        // One transaction per entity, same as themes:rebuild-aggregates: a page never sees a
        // half-rebuilt mix, and lock counts stay bounded.
        DB::transaction(function () use ($entity, $sentiment, $themes): void {
            SentimentDaily::query()->where('entity_id', $entity->id)->delete();
            $sentimentDays = SentimentObservation::query()->where('entity_id', $entity->id)
                ->selectRaw('date(observed_at) as day')->distinct()->pluck('day');
            foreach ($sentimentDays as $day) {
                $sentiment->aggregateDaily($entity->id, CarbonImmutable::parse((string) $day));
            }
            $sentiment->refreshAllSnapshots($entity->id);

            EntityThemeDaily::query()->where('entity_id', $entity->id)->delete();
            EntityThemeSnapshot::query()->where('entity_id', $entity->id)->delete();
            EntityThemeSummary::query()->where('entity_id', $entity->id)->delete();
            $themeDays = ThemeObservation::query()->where('entity_id', $entity->id)
                ->where('extractor', (string) config('themes.extractor', 'keyword'))
                ->selectRaw('date(created_at) as day')->distinct()->pluck('day');
            foreach ($themeDays as $day) {
                $themes->aggregateDaily($entity->id, CarbonImmutable::parse((string) $day));
            }
            $themes->refreshAllSnapshots($entity->id);
        });

        $this->info('Purged '.count($sourceItemIds)." opinion(s) for {$entity->name} and rebuilt its aggregates.");
    }
}
