<?php

namespace Database\Factories;

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SentimentObservation>
 */
class SentimentObservationFactory extends Factory
{
    protected $model = SentimentObservation::class;

    public function definition(): array
    {
        return [
            'entity_id' => Entity::factory(),
            'source_id' => Source::factory(),
            'source_item_id' => SourceItem::factory(),
            'sentiment' => fake()->randomElement(SentimentClass::cases()),
            'model_confidence' => 0.9500,
            'observed_at' => CarbonImmutable::now(),
        ];
    }

    public function positive(): static
    {
        return $this->state(fn () => ['sentiment' => SentimentClass::Positive]);
    }

    public function neutral(): static
    {
        return $this->state(fn () => ['sentiment' => SentimentClass::Neutral]);
    }

    public function negative(): static
    {
        return $this->state(fn () => ['sentiment' => SentimentClass::Negative]);
    }
}
