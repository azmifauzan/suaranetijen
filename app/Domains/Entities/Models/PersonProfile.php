<?php

namespace App\Domains\Entities\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PersonProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $entity_id
 * @property CarbonImmutable|null $birth_date
 * @property string|null $birth_place
 * @property string|null $occupation
 * @property string|null $affiliation
 * @property int|null $active_since_year
 * @property string|null $official_website
 * @property-read Entity $entity
 */
#[Fillable([
    'entity_id',
    'birth_date',
    'birth_place',
    'occupation',
    'affiliation',
    'active_since_year',
    'official_website',
])]
class PersonProfile extends Model
{
    /** @use HasFactory<PersonProfileFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'immutable_date',
            'active_since_year' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    protected static function newFactory(): PersonProfileFactory
    {
        return PersonProfileFactory::new();
    }
}
