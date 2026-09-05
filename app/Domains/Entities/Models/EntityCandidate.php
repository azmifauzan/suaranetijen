<?php

namespace App\Domains\Entities\Models;

use App\Models\User;
use Database\Factories\EntityCandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $normalized_term
 * @property list<string> $raw_terms
 * @property list<string> $source_types
 * @property int $frequency_score
 * @property int $unmatched_mention_count
 * @property string|null $suggested_name
 * @property int|null $suggested_category_id
 * @property string|null $suggested_entity_type
 * @property list<string>|null $suggested_aliases
 * @property int|null $suggested_parent_entity_id
 * @property string|null $reasoning
 * @property string $status
 * @property int|null $entity_id
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'normalized_term',
    'raw_terms',
    'source_types',
    'frequency_score',
    'unmatched_mention_count',
    'suggested_name',
    'suggested_category_id',
    'suggested_entity_type',
    'suggested_aliases',
    'suggested_parent_entity_id',
    'reasoning',
    'status',
    'entity_id',
    'reviewed_by',
    'reviewed_at',
])]
class EntityCandidate extends Model
{
    /** @use HasFactory<EntityCandidateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_terms' => 'array',
            'source_types' => 'array',
            'frequency_score' => 'integer',
            'unmatched_mention_count' => 'integer',
            'suggested_aliases' => 'array',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function suggestedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'suggested_category_id');
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function suggestedParentEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'suggested_parent_entity_id');
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): EntityCandidateFactory
    {
        return EntityCandidateFactory::new();
    }
}
