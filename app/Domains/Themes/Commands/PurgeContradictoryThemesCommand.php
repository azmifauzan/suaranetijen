<?php

namespace App\Domains\Themes\Commands;

use App\Domains\Search\Services\EntitySearchDocumentBuilder;
use App\Domains\Themes\Jobs\RebuildEntityAggregatesJob;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Models\ThemeObservation;
use Illuminate\Console\Command;

class PurgeContradictoryThemesCommand extends Command
{
    /**
     * Words that make a label an explicit praise. Deliberately narrow: "murah" or "ringan" can be a complaint.
     */
    private const PRAISE_WORDS = ['bagus', 'baik', 'mantap', 'jernih', 'awet', 'irit', 'nyaman', 'halus', 'stabil', 'ramah'];

    /**
     * @var string
     */
    protected $signature = 'themes:purge-contradictions {--dry-run : Count only}';

    /**
     * @var string
     */
    protected $description = 'Delete negative observations attached to an explicitly praising theme label (older extractions reused "kamera bagus" for a complaint)';

    public function handle(): int
    {
        $themeIds = Theme::query()->get(['id', 'display_label'])
            ->filter(function (Theme $theme): bool {
                $label = mb_strtolower($theme->display_label);

                return ! EntitySearchDocumentBuilder::hasNegationMarker($label)
                    && ! str_contains($label, 'kalah')
                    && preg_match('/\b('.implode('|', self::PRAISE_WORDS).')\b/u', $label) === 1;
            })
            ->pluck('id');

        $affected = [];
        $total = 0;

        foreach ($themeIds->chunk(500) as $chunk) {
            $rows = ThemeObservation::query()
                ->whereIn('theme_id', $chunk)
                ->where('sentiment', 'negative')
                ->get(['id', 'entity_id']);

            $total += $rows->count();
            foreach ($rows->pluck('entity_id')->unique() as $entityId) {
                $affected[(int) $entityId] = true;
            }

            if (! $this->option('dry-run')) {
                ThemeObservation::query()->whereIn('id', $rows->pluck('id'))->delete();
            }
        }

        if (! $this->option('dry-run')) {
            foreach (array_keys($affected) as $entityId) {
                RebuildEntityAggregatesJob::dispatch($entityId);
            }
        }

        $this->info(($this->option('dry-run') ? 'Would delete ' : 'Deleted ')."{$total} observation(s) across ".count($affected).' entity(ies).');

        return self::SUCCESS;
    }
}
