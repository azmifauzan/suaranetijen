<?php

namespace App\Domains\Sources\Services;

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\AliasPolicy;
use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Sentiment\Models\SentimentObservation;

/**
 * Picks the next entity a search-driven source (YouTube, Kaskus) should query.
 *
 * The entity list is read from the database on every call, so a newly added
 * entity is eligible on the very next cycle. A never-searched entity is always
 * due first. After that, the wait before the next search grows with how long
 * the entity has been quiet: the time since its newest published opinion (or
 * since it was added, if it has none). A product people still talk about is
 * searched often; one fading from the sources is searched less and less, up to
 * max_interval_days, and speeds back up as soon as a fresh opinion lands.
 * One call yields one search, so the API quota spent per cycle does not change.
 */
class SearchQueryPlanner
{
    /**
     * @param  array<int|string, int>  $searchedAt  entity id => unix time of its last search
     * @return array{entity_id: int, term: string}|null
     */
    public function next(array $searchedAt): ?array
    {
        $entities = Entity::query()
            ->active()
            ->searchable()
            ->with('aliases:id,entity_id,normalized_alias')
            ->get(['id', 'name', 'created_at']);

        $lastOpinionAt = SentimentObservation::query()
            ->selectRaw('entity_id, max(observed_at) as latest')
            ->groupBy('entity_id')
            ->pluck('latest', 'entity_id');

        $now = now()->getTimestamp();
        $hoursPerIdleDay = max(0, (float) config('sources.search_priority.hours_per_idle_day', 4));
        $minInterval = max(0, (int) config('sources.search_priority.min_interval_hours', 24)) * 3600;
        $maxInterval = max(0, (int) config('sources.search_priority.max_interval_days', 90)) * 86400;

        $best = null;

        foreach ($entities as $entity) {
            $term = $this->termFor($entity);

            if ($term === null) {
                continue;
            }

            $activeAt = isset($lastOpinionAt[$entity->id])
                ? strtotime((string) $lastOpinionAt[$entity->id])
                : $entity->created_at?->getTimestamp();
            $idleDays = max(0, $now - (int) ($activeAt ?: $now)) / 86400;
            $interval = (int) min($maxInterval, max($minInterval, $idleDays * $hoursPerIdleDay * 3600));

            $last = (int) ($searchedAt[$entity->id] ?? 0);
            $due = $last === 0 ? 0 : $last + $interval;
            $rank = [$due, $idleDays, $entity->id];

            if ($best === null || $rank < $best['rank']) {
                $best = ['rank' => $rank, 'entity_id' => $entity->id, 'term' => $term];
            }
        }

        return $best === null ? null : ['entity_id' => $best['entity_id'], 'term' => $best['term']];
    }

    private function termFor(Entity $entity): ?string
    {
        $terms = [$entity->name, ...$entity->aliases->pluck('normalized_alias')->all()];

        foreach ($terms as $term) {
            if (is_string($term) && AliasPolicy::isUsable(TextNormalizer::normalize($term))) {
                return $term;
            }
        }

        return null;
    }
}
