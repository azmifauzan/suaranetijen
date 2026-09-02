<?php

namespace App\Domains\Themes\Services;

use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Themes\Models\EntityThemeDaily;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\ThemeObservation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ThemeAggregator
{
    /**
     * Aggregate theme observations for a single entity and date into entity_theme_daily.
     *
     * @return Collection<int, EntityThemeDaily>
     */
    public function aggregateDaily(int $entityId, CarbonInterface $date): Collection
    {
        $startOfDay = CarbonImmutable::instance($date)->startOfDay();
        $endOfDay = CarbonImmutable::instance($date)->endOfDay();
        $dateStr = $date->format('Y-m-d');

        $observations = ThemeObservation::query()
            ->where('entity_id', $entityId)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->select('theme_id', 'sentiment', DB::raw('count(*) as count'))
            ->groupBy('theme_id', 'sentiment')
            ->get();

        $byTheme = [];
        foreach ($observations as $obs) {
            $tid = (int) $obs->theme_id;
            $byTheme[$tid] ??= [
                'positive' => 0,
                'neutral' => 0,
                'negative' => 0,
            ];

            $sentimentValue = $obs->sentiment->value;

            if ($sentimentValue === SentimentClass::Positive->value) {
                $byTheme[$tid]['positive'] += (int) $obs->count;
            } elseif ($sentimentValue === SentimentClass::Neutral->value) {
                $byTheme[$tid]['neutral'] += (int) $obs->count;
            } elseif ($sentimentValue === SentimentClass::Negative->value) {
                $byTheme[$tid]['negative'] += (int) $obs->count;
            }
        }

        $created = collect();

        foreach ($byTheme as $themeId => $counts) {
            $total = $counts['positive'] + $counts['neutral'] + $counts['negative'];

            $daily = EntityThemeDaily::query()->updateOrCreate(
                [
                    'entity_id' => $entityId,
                    'theme_id' => $themeId,
                    'date' => $dateStr,
                ],
                [
                    'positive_count' => $counts['positive'],
                    'neutral_count' => $counts['neutral'],
                    'negative_count' => $counts['negative'],
                    'observation_count' => $total,
                ]
            );

            $created->push($daily);
        }

        return $created;
    }

    /**
     * Compute and store snapshot ranking for an entity across a period window.
     */
    public function aggregateSnapshot(int $entityId, Period $period): void
    {
        $days = $period->days();
        $startDate = $days ? Carbon::now()->subDays($days)->format('Y-m-d') : null;

        // Query daily aggregates if present; fallback to observations
        $hasDaily = EntityThemeDaily::query()->where('entity_id', $entityId)->exists();

        if ($hasDaily) {
            $query = EntityThemeDaily::query()
                ->where('entity_id', $entityId);

            if ($startDate !== null) {
                $query->whereDate('date', '>=', $startDate);
            }

            $rows = $query
                ->select('theme_id')
                ->selectRaw('SUM(observation_count) as total_observations')
                ->selectRaw('SUM(positive_count) as total_positive')
                ->selectRaw('SUM(neutral_count) as total_neutral')
                ->selectRaw('SUM(negative_count) as total_negative')
                ->groupBy('theme_id')
                ->orderByDesc('total_observations')
                ->orderBy('theme_id')
                ->get();
        } else {
            $query = ThemeObservation::query()
                ->where('entity_id', $entityId);

            if ($startDate !== null) {
                $query->where('created_at', '>=', $startDate);
            }

            $rows = $query
                ->select('theme_id')
                ->selectRaw('COUNT(*) as total_observations')
                ->selectRaw("SUM(CASE WHEN sentiment = 'positive' THEN 1 ELSE 0 END) as total_positive")
                ->selectRaw("SUM(CASE WHEN sentiment = 'neutral' THEN 1 ELSE 0 END) as total_neutral")
                ->selectRaw("SUM(CASE WHEN sentiment = 'negative' THEN 1 ELSE 0 END) as total_negative")
                ->groupBy('theme_id')
                ->orderByDesc('total_observations')
                ->orderBy('theme_id')
                ->get();
        }

        $rank = 1;
        $now = Carbon::now();

        foreach ($rows as $row) {
            EntityThemeSnapshot::query()->updateOrCreate(
                [
                    'entity_id' => $entityId,
                    'theme_id' => (int) $row->theme_id,
                    'window' => $period,
                ],
                [
                    'observation_count' => (int) ($row->total_observations ?? 0),
                    'positive_count' => (int) ($row->total_positive ?? 0),
                    'neutral_count' => (int) ($row->total_neutral ?? 0),
                    'negative_count' => (int) ($row->total_negative ?? 0),
                    'rank' => $rank++,
                    'calculated_at' => $now,
                ]
            );
        }
    }

    /**
     * Refresh snapshots for all periods for an entity.
     */
    public function refreshAllSnapshots(int $entityId): void
    {
        foreach (Period::cases() as $period) {
            $this->aggregateSnapshot($entityId, $period);
        }
    }
}
