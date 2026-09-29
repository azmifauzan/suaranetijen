<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Services\RawPayloadStorage;
use App\Domains\Themes\Jobs\ExtractThemesBatchJob;
use App\Domains\Themes\Services\LlmThemeExtractor;
use Illuminate\Support\Facades\Queue;

function pendingObservation(Entity $entity, Source $source, string $text, array $overrides = []): SentimentObservation
{
    $item = SourceItem::factory()->create(['source_id' => $source->id]);
    app(RawPayloadStorage::class)->store($source, $text, $item, 'text/plain');

    return SentimentObservation::factory()->create([
        'entity_id' => $entity->id,
        'source_id' => $source->id,
        'source_item_id' => $item->id,
        'sentiment' => SentimentClass::Positive,
        'themes_extracted_at' => null,
        ...$overrides,
    ]);
}

it('sends pending opinions of one entity to the llm in a single batch', function () {
    Queue::fake();
    config(['themes.extractor' => 'llm']);
    $source = Source::factory()->create();
    $entity = Entity::factory()->create();

    foreach (range(1, 3) as $i) {
        pendingObservation($entity, $source, "Baterainya cepat habis sejak update kemarin nomor {$i}.");
    }

    $this->artisan('themes:extract-pending')->assertSuccessful();

    Queue::assertPushed(ExtractThemesBatchJob::class, 1);
    Queue::assertPushed(ExtractThemesBatchJob::class, fn ($job) => $job->entityId === $entity->id && count($job->items) === 3);
});

it('skips extracted, expired and too-short opinions and marks short ones done', function () {
    Queue::fake();
    config(['themes.extractor' => 'llm']);
    $source = Source::factory()->create();
    $entity = Entity::factory()->create();

    $ready = pendingObservation($entity, $source, 'Pelayanannya lambat dan susah dihubungi sama sekali.');
    pendingObservation($entity, $source, 'Sudah diekstrak sebelumnya oleh batch lama ya.', ['themes_extracted_at' => now()]);
    pendingObservation($entity, $source, 'Terlalu lama sudah kedaluwarsa payload-nya.', ['created_at' => now()->subHours(100)]);
    $short = pendingObservation($entity, $source, 'bagus');

    $this->artisan('themes:extract-pending')->assertSuccessful();

    Queue::assertPushed(ExtractThemesBatchJob::class, 1);
    Queue::assertPushed(ExtractThemesBatchJob::class, fn ($job) => count($job->items) === 1
        && $job->items[0]['sourceItemId'] === $ready->source_item_id);
    expect($short->fresh()->themes_extracted_at)->not->toBeNull()
        ->and($ready->fresh()->themes_extracted_at)->toBeNull();
});

it('refuses to run when the keyword extractor is active', function () {
    Queue::fake();
    config(['themes.extractor' => 'keyword']);

    $this->artisan('themes:extract-pending')->assertFailed();

    Queue::assertNothingPushed();
});

it('marks every opinion in a batch as extracted, including ones with no themes', function () {
    $source = Source::factory()->create();
    $entity = Entity::factory()->create(['name' => 'Samsung']);
    $observation = pendingObservation($entity, $source, 'Opini tanpa tema yang jelas sama sekali.');

    $this->mock(LlmThemeExtractor::class)->shouldReceive('extractBatch')->once()->andReturn([]);

    (new ExtractThemesBatchJob($entity->id, [[
        'sourceItemId' => $observation->source_item_id,
        'sourceId' => $source->id,
        'sourceDocumentHash' => null,
        'publishedAt' => null,
        'text' => 'Opini tanpa tema yang jelas sama sekali.',
    ]]))->handle(app(LlmThemeExtractor::class));

    expect($observation->fresh()->themes_extracted_at)->not->toBeNull();
});
