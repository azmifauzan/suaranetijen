<?php

namespace App\Domains\Themes\Commands;

use App\Domains\Themes\Models\EntityThemeDaily;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Services\ThemeAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RebuildThemeAggregatesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'themes:rebuild-aggregates';

    /**
     * @var string
     */
    protected $description = 'Wipe and rebuild entity_theme_daily/entity_theme_snapshots from observations of the active extractor (run once after switching THEMES_EXTRACTOR)';

    public function handle(ThemeAggregator $aggregator): int
    {
        $days = DB::table('theme_observations')
            ->where('extractor', (string) config('themes.extractor', 'keyword'))
            ->selectRaw('entity_id, date(created_at) as day')
            ->distinct()
            ->get();

        $byEntity = $days->groupBy('entity_id');
        $rebuilt = 0;
        $failed = 0;

        foreach ($byEntity as $entityId => $entityDays) {
            $entityId = (int) $entityId;

            try {
                // One transaction PER ENTITY, not one for the whole rebuild: a page for
                // this entity never sees a partial mix of old and new aggregates, while
                // the lock count per transaction stays bounded regardless of how many
                // entities/entity-days exist in total. A single all-in-one transaction
                // hit Postgres's max_locks_per_transaction on staging's real data volume
                // (confirmed live, 5 Sep 2026 LLM-backfill rollout).
                DB::transaction(function () use ($aggregator, $entityId, $entityDays): void {
                    EntityThemeDaily::query()->where('entity_id', $entityId)->delete();
                    EntityThemeSnapshot::query()->where('entity_id', $entityId)->delete();

                    foreach ($entityDays as $row) {
                        $aggregator->aggregateDaily($entityId, CarbonImmutable::parse((string) $row->day));
                    }

                    $aggregator->refreshAllSnapshots($entityId);
                });

                $rebuilt++;
            } catch (Throwable $e) {
                // One entity failing must never block the rest (same resilience posture
                // as the crawler adapters): log it and keep going.
                $failed++;
                Log::warning('themes.rebuild_aggregates_failed', [
                    'entity_id' => $entityId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Rebuilt theme aggregates for {$rebuilt} entities ({$days->count()} entity-days), {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
