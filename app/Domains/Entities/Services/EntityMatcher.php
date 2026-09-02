<?php

namespace App\Domains\Entities\Services;

use App\Domains\Entities\Models\Entity;

class EntityMatcher
{
    /**
     * Match the most specific active entity mentioned in an opinion.
     *
     * Equal-length matches for different entities are rejected as ambiguous.
     */
    public function match(string $text): ?Entity
    {
        // ponytail: scan the active entity set; add indexed candidate retrieval when volume requires it.
        $normalizedText = TextNormalizer::normalize($text);
        if ($normalizedText === '') {
            return null;
        }

        $matches = [];
        foreach (Entity::query()->active()->searchable()->with('aliases')->get() as $entity) {
            $terms = [
                TextNormalizer::normalize($entity->name),
                ...$entity->aliases->pluck('normalized_alias')->all(),
            ];

            foreach (array_unique(array_filter($terms)) as $term) {
                if ($this->containsPhrase($normalizedText, $term)) {
                    $matches[] = [
                        'entity' => $entity,
                        'length' => mb_strlen($term, 'UTF-8'),
                    ];
                }
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

            $longestEntityIds[$match['entity']->getKey()] = true;
        }

        if (count($longestEntityIds) !== 1) {
            return null;
        }

        return $matches[0]['entity'];
    }

    private function containsPhrase(string $text, string $phrase): bool
    {
        return str_contains(" {$text} ", " {$phrase} ");
    }
}
