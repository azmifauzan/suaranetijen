<?php

namespace App\Domains\Themes\Services;

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Themes\Models\EntityThemeDaily;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Models\ThemeObservation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TopThemesService
{
    /**
     * Get Top Suara Netijen for an entity according to thresholds and period window.
     *
     * @return array{
     *     has_enough_data: bool,
     *     empty_state_message: string|null,
     *     opinion_count: int,
     *     top_themes: array<int, array{id: int, slug: string, display_label: string, observation_count: int, positive_count: int, neutral_count: int, negative_count: int}>,
     *     positive_themes: array<int, array{id: int, slug: string, display_label: string, observation_count: int}>,
     *     negative_themes: array<int, array{id: int, slug: string, display_label: string, observation_count: int}>
     * }
     */
    public function getTopThemesForEntity(
        Entity $entity,
        ?Period $period = null,
        ?int $limit = null
    ): array {
        $period = $period ?? Period::OneYear;
        $limit = $limit ?? (int) config('themes.default_limit', 5);
        $minEntityOpinions = (int) config('themes.min_entity_opinions', 30);
        $minThemeOccurrences = (int) config('themes.min_theme_occurrences', 3);
        $emptyStateMessage = (string) config('themes.empty_state_message', 'Belum cukup opini untuk merangkum Suara Netijen.');

        // 1. Verify entity-level minimum qualified opinion threshold (docs/25 line 145)
        $opinionCount = $this->resolveEntityOpinionCount($entity->id, $period);

        if ($opinionCount < $minEntityOpinions) {
            return [
                'has_enough_data' => false,
                'empty_state_message' => $emptyStateMessage,
                'opinion_count' => $opinionCount,
                'top_themes' => [],
                'positive_themes' => [],
                'negative_themes' => [],
            ];
        }

        // 2. Fetch theme frequency aggregation (from snapshots or daily aggregates)
        $themeRows = $this->queryThemeFrequencies($entity->id, $period);

        // 3. Filter themes by minimum occurrence threshold (docs/25 line 147)
        $eligibleRows = $themeRows->filter(fn ($row) => $row['observation_count'] >= $minThemeOccurrences);

        if ($eligibleRows->isEmpty()) {
            return [
                'has_enough_data' => false,
                'empty_state_message' => $emptyStateMessage,
                'opinion_count' => $opinionCount,
                'top_themes' => [],
                'positive_themes' => [],
                'negative_themes' => [],
            ];
        }

        // Top themes (up to limit)
        $topThemes = $eligibleRows
            ->sortByDesc('observation_count')
            ->values()
            ->take($limit)
            ->all();

        // Positive group ("Netijen Paling Suka") - positive > negative
        $positiveThemes = $eligibleRows
            ->filter(fn ($row) => $row['positive_count'] > $row['negative_count'])
            ->sortByDesc('positive_count')
            ->values()
            ->take(5)
            ->map(fn ($row) => [
                'id' => $row['id'],
                'slug' => $row['slug'],
                'display_label' => $row['display_label'],
                'observation_count' => $row['observation_count'],
            ])
            ->all();

        // Negative group ("Paling Sering Dikeluhkan") - negative > positive
        $negativeThemes = $eligibleRows
            ->filter(fn ($row) => $row['negative_count'] > $row['positive_count'])
            ->sortByDesc('negative_count')
            ->values()
            ->take(5)
            ->map(fn ($row) => [
                'id' => $row['id'],
                'slug' => $row['slug'],
                'display_label' => $row['display_label'],
                'observation_count' => $row['observation_count'],
            ])
            ->all();

        return [
            'has_enough_data' => true,
            'empty_state_message' => null,
            'opinion_count' => $opinionCount,
            'top_themes' => $topThemes,
            'positive_themes' => $positiveThemes,
            'negative_themes' => $negativeThemes,
        ];
    }

    /**
     * Resolve total qualified opinions for an entity in the period window.
     */
    protected function resolveEntityOpinionCount(int $entityId, Period $period): int
    {
        // Try requested period snapshot first
        $snapshot = SentimentSnapshot::query()
            ->where('entity_id', $entityId)
            ->where('period', $period->value)
            ->first();

        if ($snapshot !== null) {
            return $snapshot->opinion_count;
        }

        // Try 'all' fallback if requested period has no snapshot
        if ($period !== Period::All) {
            $allSnapshot = SentimentSnapshot::query()
                ->where('entity_id', $entityId)
                ->where('period', Period::All->value)
                ->first();

            if ($allSnapshot !== null) {
                return $allSnapshot->opinion_count;
            }
        }

        // Live observations count fallback
        $days = $period->days();
        $query = SentimentObservation::query()->where('entity_id', $entityId);
        if ($days !== null) {
            $query->where('observed_at', '>=', Carbon::now()->subDays($days));
        }

        return $query->count();
    }

    /**
     * Query theme frequencies for an entity in the given period.
     *
     * @return Collection<int, array{id: int, slug: string, display_label: string, observation_count: int, positive_count: int, neutral_count: int, negative_count: int}>
     */
    protected function queryThemeFrequencies(int $entityId, Period $period): Collection
    {
        // Check snapshots first for fast reads
        $snapshots = EntityThemeSnapshot::query()
            ->with('theme')
            ->where('entity_id', $entityId)
            ->where('window', $period)
            ->orderBy('rank')
            ->get();

        if ($snapshots->isNotEmpty()) {
            return $snapshots->map(fn (EntityThemeSnapshot $snap) => [
                'id' => $snap->theme->id,
                'slug' => $snap->theme->slug,
                'display_label' => $snap->theme->display_label,
                'observation_count' => $snap->observation_count,
                'positive_count' => $snap->positive_count,
                'neutral_count' => $snap->neutral_count,
                'negative_count' => $snap->negative_count,
            ]);
        }

        // Query daily aggregates if snapshots are not precomputed
        $days = $period->days();
        $startDate = $days ? Carbon::now()->subDays($days)->format('Y-m-d') : null;

        $dailyQuery = EntityThemeDaily::query()
            ->with('theme')
            ->where('entity_id', $entityId);

        if ($startDate !== null) {
            $dailyQuery->where('date', '>=', $startDate);
        }

        $rows = $dailyQuery
            ->select('theme_id')
            ->selectRaw('SUM(observation_count) as total_observations')
            ->selectRaw('SUM(positive_count) as total_positive')
            ->selectRaw('SUM(neutral_count) as total_neutral')
            ->selectRaw('SUM(negative_count) as total_negative')
            ->groupBy('theme_id')
            ->orderByDesc('total_observations')
            ->get();

        if ($rows->isNotEmpty()) {
            return $rows->map(function ($row) {
                /** @var Theme $theme */
                $theme = Theme::find($row->theme_id);

                return [
                    'id' => $theme->id,
                    'slug' => $theme->slug,
                    'display_label' => $theme->display_label,
                    'observation_count' => (int) $row->total_observations,
                    'positive_count' => (int) $row->total_positive,
                    'neutral_count' => (int) $row->total_neutral,
                    'negative_count' => (int) $row->total_negative,
                ];
            });
        }

        // Direct fallback from theme_observations
        $obsQuery = ThemeObservation::query()
            ->where('entity_id', $entityId);

        if ($startDate !== null) {
            $obsQuery->where('created_at', '>=', $startDate);
        }

        $obsRows = $obsQuery
            ->select('theme_id')
            ->selectRaw('COUNT(*) as total_observations')
            ->selectRaw("SUM(CASE WHEN sentiment = 'positive' THEN 1 ELSE 0 END) as total_positive")
            ->selectRaw("SUM(CASE WHEN sentiment = 'neutral' THEN 1 ELSE 0 END) as total_neutral")
            ->selectRaw("SUM(CASE WHEN sentiment = 'negative' THEN 1 ELSE 0 END) as total_negative")
            ->groupBy('theme_id')
            ->orderByDesc('total_observations')
            ->get();

        return $obsRows->map(function ($row) {
            /** @var Theme $theme */
            $theme = Theme::find($row->theme_id);

            return [
                'id' => $theme->id,
                'slug' => $theme->slug,
                'display_label' => $theme->display_label,
                'observation_count' => (int) $row->total_observations,
                'positive_count' => (int) $row->total_positive,
                'neutral_count' => (int) $row->total_neutral,
                'negative_count' => (int) $row->total_negative,
            ];
        });
    }
}
