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
 * entity is eligible on the very next cycle. Each entity gets a due time of
 * `last searched + opinion_count * penalty`: a never-searched entity is always
 * due first, and the more opinions an entity already has, the longer it waits
 * before its next search. One call yields one search, so the API quota spent
 * per cycle does not change.
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
            ->get(['id', 'name']);

        $opinionCounts = SentimentObservation::query()
            ->selectRaw('entity_id, count(*) as total')
            ->groupBy('entity_id')
            ->pluck('total', 'entity_id');

        $penalty = max(0, (int) config('sources.search_priority.penalty_hours_per_opinion', 2)) * 3600;
        $maxPenalty = max(0, (int) config('sources.search_priority.max_penalty_days', 60)) * 86400;

        $best = null;

        foreach ($entities as $entity) {
            $term = $this->termFor($entity);

            if ($term === null) {
                continue;
            }

            $opinions = (int) ($opinionCounts[$entity->id] ?? 0);
            $last = (int) ($searchedAt[$entity->id] ?? 0);
            $due = $last === 0 ? 0 : $last + min($maxPenalty, $opinions * $penalty);
            $rank = [$due, $opinions, $entity->id];

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
