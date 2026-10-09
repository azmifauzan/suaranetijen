<?php

namespace App\Domains\Entities\Services;

use App\Domains\Entities\Contracts\EntityCandidateSource;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Entities\Models\EntityCandidate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates every EntityCandidateSource: merges/dedupes raw terms by
 * normalized value, cross-references unmatched_mentions as supporting
 * evidence, enriches genuinely new candidates via the LLM, and persists them
 * for admin review. A rejected/approved/pending candidate never resurfaces —
 * dismissal is permanent (see docs/superpowers/specs, "reject behavior").
 *
 * New product models from a trusted feed (config entity_candidates.auto_approve)
 * skip the review queue when their parent brand already exists, so a new phone
 * or car is crawled within days of release instead of waiting for an admin.
 */
class EntityCandidateAggregator
{
    /**
     * @var Collection<int, Entity>|null
     */
    private ?Collection $brands = null;

    /**
     * @param  list<EntityCandidateSource>  $sources
     */
    public function __construct(
        private readonly array $sources,
        private readonly EntityCandidateEnricher $enricher,
        private readonly EntityCandidateApprover $approver = new EntityCandidateApprover
    ) {}

    /**
     * @return array{created: int, auto_rejected: int, auto_approved: int}
     */
    public function scan(): array
    {
        $merged = $this->collectFromSources();
        $created = 0;
        $autoRejected = 0;
        $autoApproved = 0;

        foreach ($merged as $normalizedTerm => $data) {
            if (EntityCandidate::query()->where('normalized_term', $normalizedTerm)->exists()) {
                continue;
            }

            $rawTerms = array_values(array_unique($data['raw_terms']));
            $enrichment = $this->enricher->enrich($normalizedTerm, $rawTerms);
            $isRelevant = $enrichment['is_relevant'];
            $soldInIndonesia = $enrichment['sold_in_indonesia'];
            unset($enrichment['is_relevant'], $enrichment['sold_in_indonesia']);

            $candidate = EntityCandidate::create([
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

            if (! $isRelevant) {
                $autoRejected++;
            } elseif ($soldInIndonesia && $this->autoApprove($candidate)) {
                $autoApproved++;
            } else {
                $created++;
            }
        }

        return ['created' => $created, 'auto_rejected' => $autoRejected, 'auto_approved' => $autoApproved];
    }

    /**
     * Approve a product candidate from a trusted feed, judged sold in Indonesia,
     * when its name starts with an existing brand ("Samsung Galaxy A58" -> Samsung) and nothing already
     * claims that name. Anything less certain stays pending for an admin
     * (precision over recall).
     */
    private function autoApprove(EntityCandidate $candidate): bool
    {
        $trustedSources = (array) config('entity_candidates.auto_approve.source_types', []);
        $categorySlugs = (array) config('entity_candidates.auto_approve.category_slugs', []);
        $name = trim((string) $candidate->suggested_name);
        $normalizedName = TextNormalizer::normalize($name);

        if ($normalizedName === ''
            || $candidate->suggested_entity_type !== EntityType::Product->value
            || array_intersect($candidate->source_types, $trustedSources) === []
            || ! Category::query()->whereKey($candidate->suggested_category_id)->whereIn('slug', $categorySlugs)->exists()
            || EntityAlias::query()->whereIn('normalized_alias', [$normalizedName, $candidate->normalized_term])->exists()) {
            return false;
        }

        $parent = $this->parentBrandFor($normalizedName);

        if ($parent === null) {
            return false;
        }

        try {
            $this->approver->approve($candidate, [
                'name' => $name,
                'entity_type' => EntityType::Product->value,
                'category_id' => (int) $candidate->suggested_category_id,
                'parent_id' => $parent->id,
                'aliases' => $candidate->suggested_aliases ?? [],
            ], null);
        } catch (Throwable $e) {
            // e.g. a slug clash: leave it pending for an admin rather than abort the scan.
            Log::warning('Entity candidate auto-approval failed, left pending.', [
                'candidate_id' => $candidate->id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Longest brand name or alias that is a whole-word prefix of the product name.
     */
    private function parentBrandFor(string $normalizedName): ?Entity
    {
        $this->brands ??= Entity::query()
            ->active()
            ->where('type', EntityType::Brand)
            ->with('aliases:id,entity_id,normalized_alias')
            ->get(['id', 'name']);

        $best = null;
        $bestLength = 0;

        foreach ($this->brands as $brand) {
            foreach ([TextNormalizer::normalize($brand->name), ...$brand->aliases->pluck('normalized_alias')->all()] as $alias) {
                $alias = (string) $alias;
                if ($alias !== '' && mb_strlen($alias) > $bestLength && str_starts_with($normalizedName, $alias.' ')) {
                    $best = $brand;
                    $bestLength = mb_strlen($alias);
                }
            }
        }

        return $best;
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
