<?php

namespace App\Domains\Entities\Services;

use App\Domains\Sentiment\Models\SentimentDaily;
use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sentiment\Services\SentimentAggregator;
use App\Domains\Themes\Models\EntityThemeDaily;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\EntityThemeSummary;
use App\Domains\Themes\Models\ThemeObservation;
use App\Domains\Themes\Services\ThemeAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Wipes and rebuilds one entity's sentiment and theme aggregates from its observations, and drops
 * its stale summary. One transaction per entity, so a page never sees a half-rebuilt mix and lock
 * counts stay bounded (same reason as themes:rebuild-aggregates).
 */
class EntityAggregateRebuilder
{
    public function __construct(
        private readonly SentimentAggregator $sentiment,
        private readonly ThemeAggregator $themes,
    ) {}

    public function rebuild(int $entityId): void
    {
        DB::transaction(function () use ($entityId): void {
            SentimentDaily::query()->where('entity_id', $entityId)->delete();
            $sentimentDays = SentimentObservation::query()->where('entity_id', $entityId)
                ->selectRaw('date(observed_at) as day')->distinct()->pluck('day');
            foreach ($sentimentDays as $day) {
                $this->sentiment->aggregateDaily($entityId, CarbonImmutable::parse((string) $day));
            }
            $this->sentiment->refreshAllSnapshots($entityId);

            EntityThemeDaily::query()->where('entity_id', $entityId)->delete();
            EntityThemeSnapshot::query()->where('entity_id', $entityId)->delete();
            EntityThemeSummary::query()->where('entity_id', $entityId)->delete();
            $themeDays = ThemeObservation::query()->where('entity_id', $entityId)
                ->where('extractor', (string) config('themes.extractor', 'keyword'))
                ->selectRaw('date(created_at) as day')->distinct()->pluck('day');
            foreach ($themeDays as $day) {
                $this->themes->aggregateDaily($entityId, CarbonImmutable::parse((string) $day));
            }
            $this->themes->refreshAllSnapshots($entityId);
        });
    }
}
