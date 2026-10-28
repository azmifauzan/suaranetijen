<?php

namespace App\Domains\Entities\Models;

use App\Models\User;
use Database\Factories\EntitySubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $entity_candidate_id
 * @property string $name
 * @property string $entity_type
 * @property int $category_id
 * @property int|null $parent_entity_id
 * @property string|null $website_url
 * @property string|null $description
 * @property bool $is_owner
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EntityCandidate|null $candidate
 * @property-read Category $category
 * @property-read User $user
 */
#[Fillable([
    'user_id',
    'entity_candidate_id',
    'name',
    'entity_type',
    'category_id',
    'parent_entity_id',
    'website_url',
    'description',
    'is_owner',
])]
class EntitySubmission extends Model
{
    /** @use HasFactory<EntitySubmissionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_owner' => 'boolean'];
    }

    /**
     * @return BelongsTo<EntityCandidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(EntityCandidate::class, 'entity_candidate_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): EntitySubmissionFactory
    {
        return EntitySubmissionFactory::new();
    }
}
