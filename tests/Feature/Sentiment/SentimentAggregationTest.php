<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Sentiment\Services\SentimentAggregator;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use Carbon\CarbonImmutable;

test('Epic 3 DoD: aggregate query returns exact 70.0 for 60/20/20 hand-seeded observations', function () {
    $entity = Entity::factory()->create();
    $source = Source::factory()->create();
    $today = CarbonImmutable::now();

    // Create 60 positive, 20 neutral, 20 negative source items & observations
    $items = SourceItem::factory()->count(100)->create(['source_id' => $source->id]);

    // 60 positive observations
    for ($i = 0; $i < 60; $i++) {
        SentimentObservation::create([
            'entity_id' => $entity->id,
            'source_id' => $source->id,
            'source_item_id' => $items[$i]->id,
            'sentiment' => SentimentClass::Positive,
            'model_confidence' => 0.9500,
            'observed_at' => $today,
        ]);
    }

    // 20 neutral observations
    for ($i = 60; $i < 80; $i++) {
        SentimentObservation::create([
            'entity_id' => $entity->id,
            'source_id' => $source->id,
            'source_item_id' => $items[$i]->id,
            'sentiment' => SentimentClass::Neutral,
            'model_confidence' => 0.9500,
            'observed_at' => $today,
        ]);
    }

    // 20 negative observations
    for ($i = 80; $i < 100; $i++) {
        SentimentObservation::create([
            'entity_id' => $entity->id,
            'source_id' => $source->id,
            'source_item_id' => $items[$i]->id,
            'sentiment' => SentimentClass::Negative,
            'model_confidence' => 0.9500,
            'observed_at' => $today,
        ]);
    }

    $aggregator = app(SentimentAggregator::class);

    // 1. Verify Daily Aggregate
    $daily = $aggregator->aggregateDaily($entity->id, $today);

    expect($daily->positive_count)->toBe(60)
        ->and($daily->neutral_count)->toBe(20)
        ->and($daily->negative_count)->toBe(20)
        ->and($daily->opinion_count)->toBe(100)
        ->and((float) $daily->score)->toBe(70.0);

    // 2. Verify Snapshot Aggregate
    $snapshot = $aggregator->aggregateSnapshot($entity->id, Period::OneYear, $today);

    expect($snapshot->positive_count)->toBe(60)
        ->and($snapshot->neutral_count)->toBe(20)
        ->and($snapshot->negative_count)->toBe(20)
        ->and($snapshot->opinion_count)->toBe(100)
        ->and((float) $snapshot->score)->toBe(70.0)
        ->and($snapshot->sentiment_model_version)->toBe('v1')
        ->and($snapshot->score_formula_version)->toBe('v1');
});

test('aggregateSnapshot leaves score as null when opinion count is under public threshold (30)', function () {
    $entity = Entity::factory()->create();
    $source = Source::factory()->create();
    $today = CarbonImmutable::now();

    $items = SourceItem::factory()->count(10)->create(['source_id' => $source->id]);

    // 10 positive observations (< 30)
    for ($i = 0; $i < 10; $i++) {
        SentimentObservation::create([
            'entity_id' => $entity->id,
            'source_id' => $source->id,
            'source_item_id' => $items[$i]->id,
            'sentiment' => SentimentClass::Positive,
            'observed_at' => $today,
        ]);
    }

    $aggregator = app(SentimentAggregator::class);
    $snapshot = $aggregator->aggregateSnapshot($entity->id, Period::ThirtyDays, $today);

    expect($snapshot->opinion_count)->toBe(10)
        ->and($snapshot->score)->toBeNull();
});

test('refreshAllSnapshots populates all 4 period snapshots', function () {
    $entity = Entity::factory()->create();
    $source = Source::factory()->create();
    $today = CarbonImmutable::now();

    $item = SourceItem::factory()->create(['source_id' => $source->id]);
    SentimentObservation::create([
        'entity_id' => $entity->id,
        'source_id' => $source->id,
        'source_item_id' => $item->id,
        'sentiment' => SentimentClass::Positive,
        'observed_at' => $today,
    ]);

    $aggregator = app(SentimentAggregator::class);
    $snapshots = $aggregator->refreshAllSnapshots($entity->id, $today);

    expect($snapshots)->toHaveKeys(['30d', '90d', '365d', 'all'])
        ->and(SentimentSnapshot::where('entity_id', $entity->id)->count())->toBe(4);
});
