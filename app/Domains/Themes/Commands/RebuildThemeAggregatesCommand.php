<?php

namespace App\Domains\Themes\Commands;

use App\Domains\Themes\Models\EntityThemeDaily;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Services\ThemeAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
        EntityThemeDaily::query()->delete();
        EntityThemeSnapshot::query()->delete();

        $days = DB::table('theme_observations')
            ->where('extractor', (string) config('themes.extractor', 'keyword'))
            ->selectRaw('entity_id, date(created_at) as day')
            ->distinct()
            ->get();

        foreach ($days as $row) {
            $aggregator->aggregateDaily((int) $row->entity_id, CarbonImmutable::parse((string) $row->day));
        }

        $entityIds = $days->pluck('entity_id')->unique();
        foreach ($entityIds as $entityId) {
            $aggregator->refreshAllSnapshots((int) $entityId);
        }

        $this->info("Rebuilt theme aggregates for {$entityIds->count()} entities ({$days->count()} entity-days).");

        return self::SUCCESS;
    }
}
