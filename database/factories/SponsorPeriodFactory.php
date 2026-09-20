<?php

namespace Database\Factories;

use App\Domains\Sponsorships\Enums\SponsorPeriodStatus;
use App\Domains\Sponsorships\Models\SponsorPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SponsorPeriod>
 */
class SponsorPeriodFactory extends Factory
{
    protected $model = SponsorPeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfWeek();
        $end = now()->endOfWeek();

        return [
            'key' => fake()->unique()->slug(),
            'name' => 'Minggu '.fake()->numberBetween(1, 52).' ('.fake()->monthName().')',
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => SponsorPeriodStatus::Active,
        ];
    }

    public function closed(): self
    {
        return $this->state(fn () => [
            'status' => SponsorPeriodStatus::Closed,
        ]);
    }
}
