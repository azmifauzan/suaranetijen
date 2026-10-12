<?php

namespace App\Domains\Themes\Services;

use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Themes\Jobs\RebuildEntityAggregatesJob;
use App\Domains\Themes\Models\ThemeObservation;

/**
 * Drops opinions the LLM judged not to be about the entity (a coincidental word, not the brand), and
 * recomputes the daily sentiment aggregate so the score no longer counts them. This is the general
 * defence for ambiguous names; context_required_aliases only stops the obvious ones earlier.
 */
class OffTopicOpinionRemover
{
    /**
     * @param  list<int>  $sourceItemIds
     */
    public function remove(int $entityId, array $sourceItemIds): int
    {
        if ($sourceItemIds === []) {
            return 0;
        }

        $removed = SentimentObservation::query()
            ->where('entity_id', $entityId)
            ->whereIn('source_item_id', $sourceItemIds)
            ->delete();

        ThemeObservation::query()
            ->where('entity_id', $entityId)
            ->whereIn('source_item_id', $sourceItemIds)
            ->delete();

        if ($removed > 0) {
            RebuildEntityAggregatesJob::dispatch($entityId)->delay(now()->addMinutes(2));
        }

        return $removed;
    }
}
