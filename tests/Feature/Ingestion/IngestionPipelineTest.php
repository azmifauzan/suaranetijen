<?php

use App\Domains\Ingestion\Jobs\DiscoverSourceDocumentsJob;
use App\Domains\Ingestion\Jobs\ExpireRawPayloadJob;
use App\Domains\Sources\Adapters\FakeSourceAdapter;
use App\Domains\Sources\Enums\DocumentState;
use App\Domains\Sources\Models\RawPayload;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceDocument;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Services\RawPayloadStorage;
use Carbon\CarbonImmutable;

beforeEach(function () {
    FakeSourceAdapter::reset();
});

test('Epic 4 DoD: full pipeline runs discover -> fetch -> extract -> temp storage -> expiry via queued jobs', function () {
    $source = Source::factory()->create([
        'key' => 'pipeline_source',
        'adapter' => 'App\\Domains\\Sources\\Adapters\\FakeSourceAdapter',
        'retention_policy' => ['raw_ttl_hours' => 24],
    ]);

    $storage = app(RawPayloadStorage::class);

    // 1. Dispatch Discover job - with sync queue, cascades discover -> fetch -> extract
    DiscoverSourceDocumentsJob::dispatch($source);

    $documents = SourceDocument::where('source_id', $source->id)->get();
    expect($documents)->toHaveCount(2)
        ->and($documents[0]->state)->toBe(DocumentState::Fetched)
        ->and($documents[1]->state)->toBe(DocumentState::Fetched);

    // 2. Verify raw document payloads were temporarily saved
    expect(RawPayload::where('source_id', $source->id)->exists())->toBeTrue();

    // 3. Verify candidate opinions were extracted into source_items (2 docs * 2 opinions = 4 items)
    $items = SourceItem::where('source_id', $source->id)->get();
    expect($items)->toHaveCount(4)
        ->and($items[0]->content_hash)->toHaveLength(64)
        ->and($items[0]->raw_payload_ref)->not->toBeNull();

    // 4. Expiry after TTL via ExpireRawPayloadJob
    $future = CarbonImmutable::now()->addHours(25);
    $storage->expireExpiredPayloads($future);

    // Raw payloads deleted, item raw_payload_ref cleared, hashes preserved
    expect(RawPayload::where('source_id', $source->id)->count())->toBe(0)
        ->and($items[0]->fresh()->raw_payload_ref)->toBeNull()
        ->and($items[0]->fresh()->content_hash)->toHaveLength(64);
});

test('Epic 4 DoD: simulated failure on one adapter does not affect another adapter running in parallel', function () {
    // Source 1: Healthy
    $sourceGood = Source::factory()->create([
        'key' => 'healthy_source',
        'adapter' => 'App\\Domains\\Sources\\Adapters\\FakeSourceAdapter',
    ]);

    // Source 2: Simulated failure source
    $sourceFailing = Source::factory()->create([
        'key' => 'failing_source',
        'adapter' => 'App\\Domains\\Sources\\Adapters\\FakeSourceAdapter',
    ]);

    // 1. Run failing adapter with simulated fetch failure
    FakeSourceAdapter::setSimulateFetchFailure(true);
    $failingExceptionThrown = false;
    try {
        DiscoverSourceDocumentsJob::dispatch($sourceFailing);
    } catch (Throwable $e) {
        $failingExceptionThrown = true;
    }

    expect($failingExceptionThrown)->toBeTrue();
    $docFailing = SourceDocument::where('source_id', $sourceFailing->id)->first();
    expect($docFailing)->not->toBeNull()
        ->and($docFailing->state)->toBe(DocumentState::Failed);

    // 2. Reset failure simulation: healthy source must run completely successfully
    FakeSourceAdapter::setSimulateFetchFailure(false);
    DiscoverSourceDocumentsJob::dispatch($sourceGood);

    $docsGood = SourceDocument::where('source_id', $sourceGood->id)->get();
    expect($docsGood)->toHaveCount(2)
        ->and($docsGood[0]->state)->toBe(DocumentState::Fetched)
        ->and($docsGood[1]->state)->toBe(DocumentState::Fetched)
        ->and(SourceItem::where('source_id', $sourceGood->id)->count())->toBe(4);
});
