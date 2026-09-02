<?php

namespace App\Domains\Entities\Models;

use App\Domains\Entities\Enums\AliasType;
use App\Domains\Entities\Services\TextNormalizer;
use Database\Factories\EntityAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $entity_id
 * @property string $alias
 * @property string $normalized_alias
 * @property AliasType $alias_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Entity $entity
 */
#[Fillable(['entity_id', 'alias', 'normalized_alias', 'alias_type'])]
class EntityAlias extends Model
{
    /** @use HasFactory<EntityAliasFactory> */
    use HasFactory;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function (self $alias): void {
            if (empty($alias->normalized_alias)) {
                $alias->normalized_alias = TextNormalizer::normalize($alias->alias);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alias_type' => AliasType::class,
        ];
    }

    /**
     * Get the entity that owns this alias.
     *
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): EntityAliasFactory
    {
        return EntityAliasFactory::new();
    }
}
