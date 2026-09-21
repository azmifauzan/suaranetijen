<?php

namespace App\Domains\Sponsorships\Models;

use App\Domains\Entities\Models\Entity;
use App\Domains\Sponsorships\Enums\SponsoredEntryStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SponsoredEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $period_id
 * @property int $entity_id
 * @property int $settled_total_amount
 * @property CarbonImmutable|null $first_settled_at
 * @property SponsoredEntryStatus $status
 * @property int $clicks_count
 * @property int $views_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SponsorPeriod $period
 * @property-read Entity $entity
 * @property-read Collection<int, SponsorshipOrder> $orders
 */
#[Fillable([
    'period_id',
    'entity_id',
    'settled_total_amount',
    'first_settled_at',
    'status',
    'clicks_count',
    'views_count',
])]
class SponsoredEntry extends Model
{
    /** @use HasFactory<SponsoredEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settled_total_amount' => 'integer',
            'first_settled_at' => 'immutable_datetime',
            'status' => SponsoredEntryStatus::class,
            'clicks_count' => 'integer',
            'views_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SponsorPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(SponsorPeriod::class, 'period_id');
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    /**
     * @return HasMany<SponsorshipOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(SponsorshipOrder::class, 'sponsored_entry_id');
    }

    /**
     * Scope query to active entries.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SponsoredEntryStatus::Active);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): SponsoredEntryFactory
    {
        return SponsoredEntryFactory::new();
    }
}
