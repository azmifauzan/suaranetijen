<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\EntityMatcher;
use App\Domains\Ingestion\Jobs\MatchEntitiesJob;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sources\Enums\ProcessingState;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Models\UnmatchedMention;
use App\Domains\Sources\Services\RawPayloadStorage;

it('matches the most specific entity and writes a sentiment observation', function () {
    $brand = Entity::factory()->create(['name' => 'IDCloudHost', 'slug' => 'idcloudhost']);
    $service = Entity::factory()->create([
        'name' => 'VPS IDCloudHost',
        'slug' => 'vps-idcloudhost',
        'parent_id' => $brand->id,
    ]);
    $source = Source::factory()->create(['key' => 'diskusiwebhosting']);
    $item = SourceItem::factory()->create([
        'source_id' => $source->id,
        'content_hash' => hash('sha256', 'VPS IDCloudHost sangat stabil dan cepat'),
    ]);
    app(RawPayloadStorage::class)->store(
        $source,
        'VPS IDCloudHost sangat stabil dan cepat',
        $item,
        'text/plain'
    );

    MatchEntitiesJob::dispatch($item->id);
    MatchEntitiesJob::dispatch($item->id);

    $observation = SentimentObservation::query()->first();
    expect($observation)->not->toBeNull()
        ->and($observation->entity_id)->toBe($service->id)
        ->and($observation->sentiment)->toBe(SentimentClass::Positive)
        ->and(SentimentObservation::count())->toBe(1)
        ->and($item->fresh()->processing_state)->toBe(ProcessingState::Processed)
        ->and(UnmatchedMention::count())->toBe(0);
});

it('sends an unresolved mention to the operational table without guessing an entity', function () {
    $source = Source::factory()->create(['key' => 'indoforum']);
    $item = SourceItem::factory()->create([
        'source_id' => $source->id,
        'content_hash' => hash('sha256', 'Tidak ada nama entitas dalam opini ini'),
    ]);
    app(RawPayloadStorage::class)->store(
        $source,
        'Tidak ada nama entitas dalam opini ini',
        $item,
        'text/plain'
    );

    MatchEntitiesJob::dispatch($item->id);

    $unmatched = UnmatchedMention::query()->first();
    expect($unmatched)->not->toBeNull()
        ->and($unmatched->reason)->toBe('entity_not_resolved')
        ->and($item->fresh()->processing_state)->toBe(ProcessingState::Skipped)
        ->and(SentimentObservation::count())->toBe(0);
});

it('does not create a sentiment observation for an entity mention without an evaluation', function () {
    $entity = Entity::factory()->create(['name' => 'Biznet Gio', 'slug' => 'biznet-gio']);
    $source = Source::factory()->create(['key' => 'bluesky']);
    $item = SourceItem::factory()->create([
        'source_id' => $source->id,
        'content_hash' => hash('sha256', 'Ada yang pakai Biznet Gio?'),
    ]);
    app(RawPayloadStorage::class)->store(
        $source,
        'Ada yang pakai Biznet Gio?',
        $item,
        'text/plain'
    );

    MatchEntitiesJob::dispatch($item->id);

    expect(UnmatchedMention::query()->value('reason'))->toBe('not_an_evaluation')
        ->and($item->fresh()->processing_state)->toBe(ProcessingState::Skipped)
        ->and(SentimentObservation::count())->toBe(0)
        ->and($entity->exists)->toBeTrue();
});

it('rejects an ambiguous equal-length entity match', function () {
    Entity::factory()->create(['name' => 'Alpha One', 'slug' => 'alpha-one']);
    Entity::factory()->create(['name' => 'Alpha Two', 'slug' => 'alpha-two']);

    expect(app(EntityMatcher::class)->match('Alpha One dan Alpha Two bagus'))->toBeNull();
});
