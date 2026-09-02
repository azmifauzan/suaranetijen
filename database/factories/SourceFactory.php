<?php

namespace Database\Factories;

use App\Domains\Sources\Enums\SourceHealthState;
use App\Domains\Sources\Enums\SourceType;
use App\Domains\Sources\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    protected $model = Source::class;

    public function definition(): array
    {
        $key = fake()->unique()->slug(2);

        return [
            'key' => $key,
            'name' => fake()->company().' Source',
            'adapter' => 'App\\Domains\\Sources\\Adapters\\FakeSourceAdapter',
            'source_type' => SourceType::Forum,
            'enabled' => true,
            'priority' => fake()->numberBetween(50, 150),
            'crawl_policy' => [
                'rate_limit_per_minute' => 60,
                'request_delay_ms' => 500,
            ],
            'retention_policy' => [
                'raw_ttl_hours' => 72,
            ],
            'health_state' => SourceHealthState::Healthy,
            'last_preflight_at' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['enabled' => false]);
    }

    public function healthState(SourceHealthState $state): static
    {
        return $this->state(fn () => ['health_state' => $state]);
    }
}
