<?php

namespace Database\Factories;

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Models\SentimentDaily;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SentimentDaily>
 */
class SentimentDailyFactory extends Factory
{
    protected $model = SentimentDaily::class;

    public function definition(): array
    {
        $pos = fake()->numberBetween(10, 50);
        $neu = fake()->numberBetween(5, 20);
        $neg = fake()->numberBetween(2, 15);
        $total = $pos + $neu + $neg;
        $score = $total > 0 ? (100 * ($pos + 0.5 * $neu) / $total) : null;

        return [
            'entity_id' => Entity::factory(),
            'date' => CarbonImmutable::now()->subDays(fake()->numberBetween(0, 30)),
            'positive_count' => $pos,
            'neutral_count' => $neu,
            'negative_count' => $neg,
            'opinion_count' => $total,
            'score' => $score !== null ? round($score, 2) : null,
        ];
    }
}
