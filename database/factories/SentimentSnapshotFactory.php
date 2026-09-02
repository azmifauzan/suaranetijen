<?php

namespace Database\Factories;

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SentimentSnapshot>
 */
class SentimentSnapshotFactory extends Factory
{
    protected $model = SentimentSnapshot::class;

    public function definition(): array
    {
        $pos = fake()->numberBetween(50, 200);
        $neu = fake()->numberBetween(20, 80);
        $neg = fake()->numberBetween(10, 50);
        $total = $pos + $neu + $neg;
        $score = $total > 0 ? (100 * ($pos + 0.5 * $neu) / $total) : null;

        return [
            'entity_id' => Entity::factory(),
            'period' => Period::OneYear,
            'positive_count' => $pos,
            'neutral_count' => $neu,
            'negative_count' => $neg,
            'opinion_count' => $total,
            'score' => $score !== null ? round($score, 2) : null,
            'sentiment_model_version' => 'v1',
            'score_formula_version' => 'v1',
            'calculated_at' => CarbonImmutable::now(),
        ];
    }
}
