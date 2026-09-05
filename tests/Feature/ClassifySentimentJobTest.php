<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Ingestion\Jobs\ClassifySentimentJob;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sentiment\Jobs\UpsertSentimentObservationJob;
use App\Domains\Sentiment\Services\SentimentClassifier;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Models\UnmatchedMention;
use App\Domains\Sources\Services\RawPayloadStorage;
use App\Domains\Themes\Jobs\ExtractThemesJob;
use Illuminate\Support\Facades\Queue;

it('dispatches both sentiment observation and theme extraction from the same classified opinion', function () {
    Queue::fake();

    $entity = Entity::factory()->create();
    $source = Source::factory()->create();
    $item = SourceItem::factory()->create([
        'source_id' => $source->id,
        'content_hash' => hash('sha256', 'Pelayanannya sangat bagus dan memuaskan'),
    ]);
    app(RawPayloadStorage::class)->store(
        $source,
        'Pelayanannya sangat bagus dan memuaskan',
        $item,
        'text/plain'
    );

    (new ClassifySentimentJob($item->id, $entity->id))->handle(app(SentimentClassifier::class));

    Queue::assertPushed(UpsertSentimentObservationJob::class, fn ($job) => $job->entityId === $entity->id);

    Queue::assertPushed(ExtractThemesJob::class, fn ($job) => $job->entityId === $entity->id
        && $job->sourceId === $source->id
        && $job->sourceItemId === $item->id
        && $job->text === 'Pelayanannya sangat bagus dan memuaskan'
        && $job->sourceDocumentHash === $item->content_hash
        && $job->contextSentiment === SentimentClass::Positive);
});

it('does not dispatch theme extraction when the text is not an evaluation', function () {
    Queue::fake();

    $entity = Entity::factory()->create();
    $source = Source::factory()->create();
    $item = SourceItem::factory()->create([
        'source_id' => $source->id,
        'content_hash' => hash('sha256', 'Ada yang pakai produk ini?'),
    ]);
    app(RawPayloadStorage::class)->store(
        $source,
        'Ada yang pakai produk ini?',
        $item,
        'text/plain'
    );

    (new ClassifySentimentJob($item->id, $entity->id))->handle(app(SentimentClassifier::class));

    Queue::assertNotPushed(ExtractThemesJob::class);
    Queue::assertNotPushed(UpsertSentimentObservationJob::class);
    expect(UnmatchedMention::query()->value('reason'))->toBe('not_an_evaluation');
});
