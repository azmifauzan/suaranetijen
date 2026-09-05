<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Services\RawPayloadStorage;
use App\Domains\Themes\Jobs\ExtractThemesJob;
use Illuminate\Support\Facades\Queue;

it('dispatches theme extraction for existing observations whose raw payload still exists', function () {
    Queue::fake();

    $entity = Entity::factory()->create();
    $source = Source::factory()->create();
    $item = SourceItem::factory()->create(['source_id' => $source->id]);
    app(RawPayloadStorage::class)->store($source, 'Servernya ngebut banget!', $item, 'text/plain');
    SentimentObservation::factory()->create([
        'entity_id' => $entity->id,
        'source_id' => $source->id,
        'source_item_id' => $item->id,
        'sentiment' => SentimentClass::Positive,
    ]);

    $expiredItem = SourceItem::factory()->create(['source_id' => $source->id]);
    SentimentObservation::factory()->create([
        'entity_id' => $entity->id,
        'source_id' => $source->id,
        'source_item_id' => $expiredItem->id,
        'sentiment' => SentimentClass::Positive,
    ]);

    $this->artisan('themes:backfill')->assertSuccessful();

    Queue::assertPushed(ExtractThemesJob::class, 1);
    Queue::assertPushed(ExtractThemesJob::class, fn ($job) => $job->entityId === $entity->id
        && $job->sourceItemId === $item->id
        && $job->text === 'Servernya ngebut banget!');
});
