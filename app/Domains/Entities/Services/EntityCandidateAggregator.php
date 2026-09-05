<?php

namespace App\Domains\Entities\Services;

use App\Domains\Entities\Contracts\EntityCandidateSource;
use App\Domains\Entities\Models\EntityCandidate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates every EntityCandidateSource: merges/dedupes raw terms by
 * normalized value, cross-references unmatched_mentions as supporting
 * evidence, enriches genuinely new candidates via the LLM, and persists them
 * for admin review. A rejected/approved/pending candidate never resurfaces —
 * dismissal is permanent (see docs/superpowers/specs, "reject behavior").
 */
class EntityCandidateAggregator
{
    /**
     * @param  list<EntityCandidateSource>  $sources
     */
    public function __construct(
        private readonly array $sources,
        private readonly EntityCandidateEnricher $enricher
    ) {}

    /**
     * @return array{created: int, auto_rejected: int}
     */
    public function scan(): array
    {
        $merged = $this->collectFromSources();
        $created = 0;
        $autoRejected = 0;

        foreach ($merged as $normalizedTerm => $data) {
            if (EntityCandidate::query()->where('normalized_term', $normalizedTerm)->exists()) {
                continue;
            }

            $rawTerms = array_values(array_unique($data['raw_terms']));
            $enrichment = $this->enricher->enrich($normalizedTerm, $rawTerms);
            $isRelevant = $enrichment['is_relevant'];
            unset($enrichment['is_relevant']);

            EntityCandidate::create([
                'normalized_term' => $normalizedTerm,
                'raw_terms' => $rawTerms,
                'source_types' => array_values(array_unique($data['source_types'])),
                'frequency_score' => $data['weight'],
                'unmatched_mention_count' => $this->countUnmatchedMentions($normalizedTerm),
                // The LLM already judged this isn't a brand/product/service (sports
                // results, news, schedules, politics, etc.) — auto-reject instead of
                // putting noise in front of the admin, but still record it so this
                // exact term is never re-enriched (and re-billed) on a later scan.
                'status' => $isRelevant ? 'pending' : 'rejected',
                ...$enrichment,
            ]);

            if ($isRelevant) {
                $created++;
            } else {
                $autoRejected++;
            }
        }

        return ['created' => $created, 'auto_rejected' => $autoRejected];
    }

    /**
     * @return array<string, array{raw_terms: list<string>, source_types: list<string>, weight: int}>
     */
    private function collectFromSources(): array
    {
        $merged = [];

        foreach ($this->sources as $source) {
            try {
                $items = $source->discover();
            } catch (Throwable $e) {
                Log::warning('EntityCandidateSource failed, skipping.', [
                    'source_type' => $source->sourceType(),
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                continue;
            }

            foreach ($items as $item) {
                $normalized = TextNormalizer::normalize($item['raw_term']);
                if ($normalized === '') {
                    continue;
                }

                $merged[$normalized]['raw_terms'][] = $item['raw_term'];
                $merged[$normalized]['source_types'][] = $source->sourceType();
                $merged[$normalized]['weight'] = ($merged[$normalized]['weight'] ?? 0) + $item['weight'];
            }
        }

        return $merged;
    }

    /**
     * Count raw crawled payloads mentioning this term as supporting evidence
     * — a booster on top of search_queries/external feeds, not a candidate
     * source on its own. O(candidates x raw_payloads); acceptable only at
     * weekly-batch, low-candidate-count scale (see docs/superpowers specs).
     */
    private function countUnmatchedMentions(string $normalizedTerm): int
    {
        return (int) DB::table('raw_payloads')
            ->join('unmatched_mentions', 'unmatched_mentions.source_item_id', '=', 'raw_payloads.source_item_id')
            ->whereRaw('lower(raw_payloads.payload) LIKE ?', ['%'.mb_strtolower($normalizedTerm).'%'])
            ->count();
    }
}
