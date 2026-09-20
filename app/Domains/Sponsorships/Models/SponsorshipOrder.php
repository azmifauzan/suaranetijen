<?php

namespace App\Domains\Sponsorships\Models;

use App\Domains\Sponsorships\Enums\SponsorshipOrderStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\SponsorshipOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sponsored_entry_id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_order_id
 * @property string|null $provider_payment_id
 * @property string|null $payment_link_url
 * @property int $amount
 * @property SponsorshipOrderStatus $status
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $paid_at
 * @property array<string, mixed>|null $audit_payload
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SponsoredEntry $sponsoredEntry
 * @property-read User $user
 */
#[Fillable([
    'sponsored_entry_id',
    'user_id',
    'provider',
    'provider_order_id',
    'provider_payment_id',
    'payment_link_url',
    'amount',
    'status',
    'expires_at',
    'paid_at',
    'audit_payload',
])]
class SponsorshipOrder extends Model
{
    /** @use HasFactory<SponsorshipOrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => SponsorshipOrderStatus::class,
            'expires_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'audit_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SponsoredEntry, $this>
     */
    public function sponsoredEntry(): BelongsTo
    {
        return $this->belongsTo(SponsoredEntry::class, 'sponsored_entry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the order is in pending state.
     */
    public function isPending(): bool
    {
        return $this->status === SponsorshipOrderStatus::Pending;
    }

    /**
     * Check if the order is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === SponsorshipOrderStatus::Paid;
    }

    /**
     * Scope query to paid orders.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', SponsorshipOrderStatus::Paid);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): SponsorshipOrderFactory
    {
        return SponsorshipOrderFactory::new();
    }
}
