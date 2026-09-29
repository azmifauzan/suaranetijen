<?php

namespace App\Domains\Entities\Services;

use App\Domains\Entities\Models\Entity;
use Illuminate\Support\Facades\Cache;

class EntityMatcher
{
    /**
     * Match the most specific active entity mentioned in an opinion.
     *
     * Equal-length matches for different entities are rejected as ambiguous.
     */
    public function match(string $text): ?Entity
    {
        return $this->matchWithTerm($text)['entity'] ?? null;
    }

    /**
     * Same as match(), also returning the alias or name phrase that matched.
     *
     * @return array{entity: Entity, term: string}|null
     */
    public function matchWithTerm(string $text): ?array
    {
        // ponytail: scan the active entity set; add indexed candidate retrieval when volume requires it.
        $normalizedText = TextNormalizer::normalize($text);
        if ($normalizedText === '') {
            return null;
        }

        $matches = [];
        foreach ($this->candidates() as $candidate) {
            $name = $candidate['name'];
            $terms = [$name, ...$candidate['aliases']];

            foreach (array_unique(array_filter($terms)) as $term) {
                if (! AliasPolicy::isUsable($term) || ! $this->containsPhrase($normalizedText, $term)) {
                    continue;
                }

                if ($term !== $name && AliasPolicy::requiresUppercase($term)
                    && ! AliasPolicy::appearsUppercase($text, $term)) {
                    continue;
                }

                $matches[] = [
                    'entityId' => $candidate['id'],
                    'term' => $term,
                    'length' => mb_strlen($term, 'UTF-8'),
                ];
            }
        }

        if ($matches === []) {
            return null;
        }

        usort($matches, static fn (array $left, array $right): int => $right['length'] <=> $left['length']);
        $longestLength = $matches[0]['length'];
        $longestEntityIds = [];

        foreach ($matches as $match) {
            if ($match['length'] !== $longestLength) {
                break;
            }

            $longestEntityIds[$match['entityId']] = true;
        }

        if (count($longestEntityIds) !== 1) {
            return null;
        }

        $entity = Entity::query()->find($matches[0]['entityId']);

        return $entity === null ? null : ['entity' => $entity, 'term' => $matches[0]['term']];
    }

    /**
     * Normalized name and aliases of every active, searchable entity. Loading the whole set
     * cost ~2s per call, so it is cached briefly (a new alias applies within the TTL).
     *
     * @return array<int, array{id: int, name: string, aliases: array<int, string>}>
     */
    private function candidates(): array
    {
        $load = static fn (): array => Entity::query()->active()->searchable()->with('aliases')->get()
            ->map(static fn (Entity $entity): array => [
                'id' => (int) $entity->getKey(),
                'name' => TextNormalizer::normalize($entity->name),
                'aliases' => $entity->aliases->map(static fn ($alias): string => (string) $alias->normalized_alias)->values()->all(),
            ])
            ->values()
            ->all();

        $ttl = (int) config('entity_matching.candidates_cache_seconds', 60);

        return $ttl > 0 ? Cache::remember('entity-matcher:candidates', $ttl, $load) : $load();
    }

    private function containsPhrase(string $text, string $phrase): bool
    {
        if (! AliasPolicy::isModelNumber($phrase)) {
            return str_contains(" {$text} ", " {$phrase} ");
        }

        return preg_match(AliasPolicy::standaloneModelPattern($phrase), $text) === 1;
    }
}
