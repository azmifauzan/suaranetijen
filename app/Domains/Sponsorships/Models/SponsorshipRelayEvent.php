<?php

namespace App\Domains\Sponsorships\Models;

use App\Domains\Sponsorships\Enums\SponsorshipRelayEventStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $svix_id
 * @property string $event_type
 * @property string|null $environment
 * @property array<string, mixed> $payload
 * @property SponsorshipRelayEventStatus $status
 * @property string|null $last_error
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'svix_id',
    'event_type',
    'environment',
    'payload',
    'status',
    'last_error',
    'processed_at',
])]
class SponsorshipRelayEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => SponsorshipRelayEventStatus::class,
            'processed_at' => 'immutable_datetime',
        ];
    }

    /**
     * Determine if this event was already delivered.
     */
    public function isDelivered(): bool
    {
        return $this->status === SponsorshipRelayEventStatus::Delivered;
    }

    /**
     * Determine if this event was ignored.
     */
    public function isIgnored(): bool
    {
        return $this->status === SponsorshipRelayEventStatus::Ignored;
    }
}
