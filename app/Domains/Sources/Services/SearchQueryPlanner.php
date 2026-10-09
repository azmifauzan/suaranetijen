<?php

namespace App\Domains\Sources\Services;

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\AliasPolicy;
use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Sentiment\Models\SentimentObservation;
use Illuminate\Database\Eloquent\Collection;

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
 *
 * Focus mode (config search_priority.focus_types, e.g. "product") narrows the
 * pool to those entity types while any exist, and puts entities that are in
 * play first: an opinion in the last focus_active_days, or added within
 * focus_new_days and never searched. Everything else only gets a turn once no
 * in-play entity is left, so a month of searches is not spent on 3,000
 * dormant models.
 */
class SearchQueryPlanner
{
    /**
     * @param  array<int|string, int>  $searchedAt  entity id => unix time of its last search
     * @return array{entity_id: int, term: string}|null
     */
    public function next(array $searchedAt): ?array
    {
        $focusTypes = array_values(array_filter((array) config('sources.search_priority.focus_types', [])));
        $entities = $this->entities($focusTypes);

        if ($entities->isEmpty() && $focusTypes !== []) {
            $focusTypes = [];
            $entities = $this->entities([]);
        }

        $lastOpinionAt = SentimentObservation::query()
            ->selectRaw('entity_id, max(observed_at) as latest')
            ->groupBy('entity_id')
            ->pluck('latest', 'entity_id');

        $now = now()->getTimestamp();
        $hoursPerIdleDay = max(0, (float) config('sources.search_priority.hours_per_idle_day', 4));
        $minInterval = max(0, (int) config('sources.search_priority.min_interval_hours', 24)) * 3600;
        $maxInterval = max(0, (int) config('sources.search_priority.max_interval_days', 90)) * 86400;

        $activeSince = $now - max(0, (int) config('sources.search_priority.focus_active_days', 90)) * 86400;
        $newSince = $now - max(0, (int) config('sources.search_priority.focus_new_days', 60)) * 86400;

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
            $inPlay = $focusTypes === []
                || (int) $activeAt >= $activeSince && isset($lastOpinionAt[$entity->id])
                || $last === 0 && (int) $entity->created_at?->getTimestamp() >= $newSince;
            $rank = [$inPlay ? 0 : 1, $due, $idleDays, $entity->id];

            if ($best === null || $rank < $best['rank']) {
                $best = ['rank' => $rank, 'entity_id' => $entity->id, 'term' => $term];
            }
        }

        return $best === null ? null : ['entity_id' => $best['entity_id'], 'term' => $best['term']];
    }

    /**
     * @param  list<string>  $types
     * @return Collection<int, Entity>
     */
    private function entities(array $types): Collection
    {
        return Entity::query()
            ->active()
            ->searchable()
            ->when($types !== [], fn ($query) => $query->whereIn('type', $types))
            ->with('aliases:id,entity_id,normalized_alias')
            ->get(['id', 'name', 'created_at']);
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
