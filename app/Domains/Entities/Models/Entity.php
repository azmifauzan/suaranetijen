<?php

namespace App\Domains\Entities\Models;

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Ratings\Models\RatingSnapshot;
use App\Domains\Ratings\Models\UserRating;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use Database\Factories\EntityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $category_id
 * @property int|null $parent_id
 * @property EntityType $type
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property EntityStatus $status
 * @property bool $searchable
 * @property bool $rankable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category $category
 * @property-read Entity|null $parent
 * @property-read Collection<int, Entity> $children
 * @property-read Collection<int, EntityAlias> $aliases
 * @property-read Collection<int, UserRating> $ratings
 * @property-read RatingSnapshot|null $ratingSnapshot
 * @property-read SmartphoneSpec|null $smartphoneSpec
 * @property-read CarSpec|null $carSpec
 * @property-read MotorcycleSpec|null $motorcycleSpec
 * @property-read PersonProfile|null $personProfile
 */
#[Fillable([
    'category_id',
    'parent_id',
    'type',
    'name',
    'slug',
    'description',
    'status',
    'searchable',
    'rankable',
])]
class Entity extends Model
{
    /** @use HasFactory<EntityFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EntityType::class,
            'status' => EntityStatus::class,
            'searchable' => 'boolean',
            'rankable' => 'boolean',
        ];
    }

    /**
     * Get the category that this entity belongs to.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the parent entity (e.g. brand for a product/service).
     *
     * @return BelongsTo<Entity, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the child entities (e.g. products or services of a brand).
     *
     * @return HasMany<Entity, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Get the aliases associated with this entity.
     *
     * @return HasMany<EntityAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(EntityAlias::class);
    }

    /**
     * Get the first-party ratings for this entity.
     *
     * @return HasMany<UserRating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(UserRating::class);
    }

    /**
     * Get the current first-party rating snapshot.
     *
     * @return HasOne<RatingSnapshot, $this>
     */
    public function ratingSnapshot(): HasOne
    {
        return $this->hasOne(RatingSnapshot::class);
    }

    /**
     * Get the sentiment snapshots for this entity.
     *
     * @return HasMany<SentimentSnapshot, $this>
     */
    public function sentimentSnapshots(): HasMany
    {
        return $this->hasMany(SentimentSnapshot::class);
    }

    /**
     * Manually curated reference specs for Smartphone-category entities.
     * Static admin-entered data, never derived from sentiment (ADR-008).
     *
     * @return HasOne<SmartphoneSpec, $this>
     */
    public function smartphoneSpec(): HasOne
    {
        return $this->hasOne(SmartphoneSpec::class);
    }

    /**
     * Manually curated reference specs for Mobil-category entities.
     *
     * @return HasOne<CarSpec, $this>
     */
    public function carSpec(): HasOne
    {
        return $this->hasOne(CarSpec::class);
    }

    /**
     * Manually curated reference specs for Motor-category entities.
     *
     * @return HasOne<MotorcycleSpec, $this>
     */
    public function motorcycleSpec(): HasOne
    {
        return $this->hasOne(MotorcycleSpec::class);
    }

    /**
     * Manually curated reference profile for Tokoh Publik (person-type) entities.
     *
     * @return HasOne<PersonProfile, $this>
     */
    public function personProfile(): HasOne
    {
        return $this->hasOne(PersonProfile::class);
    }

    /**
     * Scope query to only active entities.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', EntityStatus::Active);
    }

    /**
     * Scope query to only searchable entities.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeSearchable(Builder $query): Builder
    {
        return $query->where('searchable', true);
    }

    /**
     * Scope query to only rankable entities.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeRankable(Builder $query): Builder
    {
        return $query->where('rankable', true);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): EntityFactory
    {
        return EntityFactory::new();
    }
}
