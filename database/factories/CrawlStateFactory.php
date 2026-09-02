<?php

namespace Database\Factories;

use App\Domains\Sources\Models\CrawlState;
use App\Domains\Sources\Models\Source;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrawlState>
 */
class CrawlStateFactory extends Factory
{
    protected $model = CrawlState::class;

    public function definition(): array
    {
        return [
            'source_id' => Source::factory(),
            'cursor_key' => 'default',
            'cursor_value' => 'page_1',
            'last_external_id' => fake()->numerify('ext-####'),
            'last_crawled_at' => CarbonImmutable::now(),
            'metadata' => ['page' => 1],
        ];
    }
}
