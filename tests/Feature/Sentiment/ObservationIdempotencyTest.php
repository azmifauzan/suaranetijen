<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

test('sentiment_observations unique constraint prevents duplicate observation for same (entity_id, source_item_id)', function () {
    $entity = Entity::factory()->create();
    $source = Source::factory()->create();
    $item = SourceItem::factory()->create(['source_id' => $source->id]);

    // First insert succeeds
    SentimentObservation::create([
        'entity_id' => $entity->id,
        'source_id' => $source->id,
        'source_item_id' => $item->id,
        'sentiment' => SentimentClass::Positive,
        'observed_at' => CarbonImmutable::now(),
    ]);

    // Duplicate insert for same (entity_id, source_item_id) MUST throw QueryException
    expect(function () use ($entity, $source, $item) {
        SentimentObservation::create([
            'entity_id' => $entity->id,
            'source_id' => $source->id,
            'source_item_id' => $item->id,
            'sentiment' => SentimentClass::Neutral,
            'observed_at' => CarbonImmutable::now(),
        ]);
    })->toThrow(QueryException::class);
});
