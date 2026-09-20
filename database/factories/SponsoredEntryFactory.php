<?php

namespace Database\Factories;

use App\Domains\Entities\Models\Entity;
use App\Domains\Sponsorships\Enums\SponsoredEntryStatus;
use App\Domains\Sponsorships\Models\SponsoredEntry;
use App\Domains\Sponsorships\Models\SponsorPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SponsoredEntry>
 */
class SponsoredEntryFactory extends Factory
{
    protected $model = SponsoredEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'period_id' => SponsorPeriod::factory(),
            'entity_id' => Entity::factory(),
            'settled_total_amount' => 50000,
            'first_settled_at' => now(),
            'status' => SponsoredEntryStatus::Active,
            'clicks_count' => 0,
        ];
    }
}
