<?php

namespace Database\Factories;

use App\Domains\Sponsorships\Enums\SponsorshipOrderStatus;
use App\Domains\Sponsorships\Models\SponsoredEntry;
use App\Domains\Sponsorships\Models\SponsorshipOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SponsorshipOrder>
 */
class SponsorshipOrderFactory extends Factory
{
    protected $model = SponsorshipOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sponsored_entry_id' => SponsoredEntry::factory(),
            'user_id' => User::factory(),
            'provider' => 'sumopod',
            'provider_order_id' => 'SNT-SPN-'.fake()->unique()->numerify('#####'),
            'provider_payment_id' => null,
            'payment_link_url' => 'https://checkout.sumopod.com/pay/'.fake()->slug(),
            'amount' => 50000,
            'status' => SponsorshipOrderStatus::Pending,
            'expires_at' => now()->addHours(24),
        ];
    }

    public function paid(): self
    {
        return $this->state(fn () => [
            'status' => SponsorshipOrderStatus::Paid,
            'provider_payment_id' => 'pay_'.fake()->unique()->lexify('????????????'),
            'paid_at' => now(),
        ]);
    }
}
