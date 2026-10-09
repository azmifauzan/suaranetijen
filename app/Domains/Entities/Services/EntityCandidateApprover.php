<?php

namespace App\Domains\Entities\Services;

use App\Domains\Entities\Enums\AliasType;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Entities\Models\EntityCandidate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns an entity candidate into a real Entity (+ primary alias + any extra
 * aliases) and links the candidate to it. Shared by the admin review queue and
 * the weekly scan's auto-approval path.
 */
class EntityCandidateApprover
{
    /**
     * @param  array{name: string, entity_type: string, category_id: int, parent_id?: int|null, aliases?: list<string>|null}  $fields
     */
    public function approve(EntityCandidate $candidate, array $fields, ?int $reviewerId): Entity
    {
        return DB::transaction(function () use ($candidate, $fields, $reviewerId): Entity {
            $entity = Entity::create([
                'category_id' => $fields['category_id'],
                'parent_id' => $fields['parent_id'] ?? null,
                'type' => $fields['entity_type'],
                'name' => $fields['name'],
                'slug' => Str::slug($fields['name']),
                'status' => EntityStatus::Active,
            ]);

            $primaryNormalized = TextNormalizer::normalize($entity->name);

            EntityAlias::create([
                'entity_id' => $entity->id,
                'alias' => $entity->name,
                'normalized_alias' => $primaryNormalized,
                'alias_type' => AliasType::Primary,
            ]);

            $seenNormalized = [$primaryNormalized => true];

            foreach (($fields['aliases'] ?? []) as $alias) {
                $alias = trim((string) $alias);
                if ($alias === '') {
                    continue;
                }

                $normalized = TextNormalizer::normalize($alias);
                if (! AliasPolicy::isUsable($normalized) || isset($seenNormalized[$normalized])) {
                    continue;
                }
                $seenNormalized[$normalized] = true;

                EntityAlias::create([
                    'entity_id' => $entity->id,
                    'alias' => $alias,
                    'normalized_alias' => $normalized,
                    'alias_type' => AliasType::CommonVariant,
                ]);
            }

            $candidate->update([
                'status' => 'approved',
                'entity_id' => $entity->id,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);

            return $entity;
        });
    }
}
