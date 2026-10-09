<?php

namespace App\Domains\Themes\Services;

use App\Domains\Sentiment\Jobs\AggregateDailySentimentJob;
use App\Domains\Sentiment\Models\SentimentObservation;
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

        $observations = SentimentObservation::query()
            ->where('entity_id', $entityId)
            ->whereIn('source_item_id', $sourceItemIds);

        $days = (clone $observations)->pluck('observed_at')
            ->map(fn ($at) => $at->format('Y-m-d'))
            ->unique();

        $removed = $observations->delete();

        ThemeObservation::query()
            ->where('entity_id', $entityId)
            ->whereIn('source_item_id', $sourceItemIds)
            ->delete();

        foreach ($days as $day) {
            AggregateDailySentimentJob::dispatch($entityId, $day);
        }

        return $removed;
    }
}
