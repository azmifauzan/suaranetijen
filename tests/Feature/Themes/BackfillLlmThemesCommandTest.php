<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Services\RawPayloadStorage;
use App\Domains\Themes\Jobs\ExtractThemesBatchJob;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Models\ThemeObservation;
use Illuminate\Support\Facades\Queue;

function backfillBatchObservation(Entity $entity, Source $source, string $text = 'Baterainya cepat habis sejak update kemarin, kecewa banget.'): SentimentObservation
{
    $item = SourceItem::factory()->create(['source_id' => $source->id]);
    app(RawPayloadStorage::class)->store($source, $text, $item, 'text/plain');

    return SentimentObservation::factory()->create([
        'entity_id' => $entity->id,
        'source_id' => $source->id,
        'source_item_id' => $item->id,
        'sentiment' => SentimentClass::Positive,
    ]);
}

it('only batches entities meeting the minimum opinion count', function () {
    Queue::fake();
    config(['themes.extractor' => 'llm']);
    $source = Source::factory()->create();
    $eligible = Entity::factory()->create();
    $ineligible = Entity::factory()->create();

    foreach (range(1, 3) as $i) {
        backfillBatchObservation($eligible, $source);
    }
    backfillBatchObservation($ineligible, $source);

    $this->artisan('themes:backfill-batch', ['--entity-min-opinions' => 3])->assertSuccessful();

    Queue::assertPushed(ExtractThemesBatchJob::class, 1);
    Queue::assertPushed(ExtractThemesBatchJob::class, fn ($job) => $job->entityId === $eligible->id && count($job->items) === 3);
});

it('caps opinions per entity to the most recent N', function () {
    Queue::fake();
    config(['themes.extractor' => 'llm']);
    $source = Source::factory()->create();
    $entity = Entity::factory()->create();

    foreach (range(1, 5) as $i) {
        backfillBatchObservation($entity, $source)->update(['observed_at' => now()->subDays(5 - $i)]);
    }

    $this->artisan('themes:backfill-batch', ['--entity-min-opinions' => 1, '--entity-cap' => 2, '--batch-size' => 10])
        ->assertSuccessful();

    Queue::assertPushed(ExtractThemesBatchJob::class, fn ($job) => count($job->items) === 2);
});

it('splits opinions into multiple batches of the configured size', function () {
    Queue::fake();
    config(['themes.extractor' => 'llm']);
    $source = Source::factory()->create();
    $entity = Entity::factory()->create();

    foreach (range(1, 5) as $i) {
        backfillBatchObservation($entity, $source);
    }

    $this->artisan('themes:backfill-batch', ['--entity-min-opinions' => 1, '--batch-size' => 2])
        ->assertSuccessful();

    Queue::assertPushed(ExtractThemesBatchJob::class, 3);
});

it('skips opinions with no payload, too-short text, or already extracted by llm', function () {
    Queue::fake();
    config(['themes.extractor' => 'llm', 'themes.llm_min_chars' => 30]);
    $source = Source::factory()->create();
    $entity = Entity::factory()->create();

    $good = backfillBatchObservation($entity, $source);
    $short = backfillBatchObservation($entity, $source, 'mantap');
    $done = backfillBatchObservation($entity, $source);
    ThemeObservation::create([
        'entity_id' => $entity->id,
        'theme_id' => Theme::create(['slug' => 'x', 'display_label' => 'X', 'canonical_key' => 'x'])->id,
        'source_id' => $done->source_id,
        'source_item_id' => $done->source_item_id,
        'sentiment' => SentimentClass::Positive,
        'extractor' => 'llm',
    ]);
    $noPayload = SentimentObservation::factory()->create([
        'entity_id' => $entity->id,
        'source_id' => $source->id,
        'source_item_id' => SourceItem::factory()->create(['source_id' => $source->id])->id,
        'sentiment' => SentimentClass::Positive,
    ]);

    $this->artisan('themes:backfill-batch', ['--entity-min-opinions' => 1])->assertSuccessful();

    Queue::assertPushed(ExtractThemesBatchJob::class, fn ($job) => count($job->items) === 1
        && $job->items[0]['sourceItemId'] === $good->source_item_id);
});

it('does nothing but count on --dry-run', function () {
    Queue::fake();
    config(['themes.extractor' => 'llm']);
    $source = Source::factory()->create();
    $entity = Entity::factory()->create();
    backfillBatchObservation($entity, $source);

    $this->artisan('themes:backfill-batch', ['--entity-min-opinions' => 1, '--dry-run' => true])
        ->expectsOutputToContain('Would dispatch 1 batch job(s) covering 1 opinion(s)')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('refuses to run when the extractor is not llm', function () {
    config(['themes.extractor' => 'keyword']);

    $this->artisan('themes:backfill-batch')->assertFailed();
});
