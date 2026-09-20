<?php

namespace App\Domains\Sponsorships\Models;

use App\Domains\Sponsorships\Enums\SponsorPeriodStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SponsorPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property SponsorPeriodStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, SponsoredEntry> $entries
 */
#[Fillable([
    'key',
    'name',
    'starts_at',
    'ends_at',
    'status',
])]
class SponsorPeriod extends Model
{
    /** @use HasFactory<SponsorPeriodFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'status' => SponsorPeriodStatus::class,
        ];
    }

    /**
     * @return HasMany<SponsoredEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(SponsoredEntry::class, 'period_id');
    }

    /**
     * Scope query to active periods.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SponsorPeriodStatus::Active);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): SponsorPeriodFactory
    {
        return SponsorPeriodFactory::new();
    }
}
