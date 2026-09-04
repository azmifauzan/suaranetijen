<?php

use App\Domains\Ingestion\Jobs\DiscoverSourceDocumentsJob;
use App\Domains\Ingestion\Jobs\FetchSourceDocumentJob;
use App\Domains\Sources\Adapters\FakeSourceAdapter;
use App\Domains\Sources\Enums\DocumentState;
use App\Domains\Sources\Models\IngestionFailure;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceDocument;
use App\Domains\Sources\Services\SourceRateLimiter;

beforeEach(function () {
    FakeSourceAdapter::reset();
});

test('FetchSourceDocumentJob releases instead of permanently failing when its own rate limit is hit', function () {
    $source = Source::factory()->create([
        'key' => 'rate_limited_fetch_source',
        'adapter' => 'App\\Domains\\Sources\\Adapters\\FakeSourceAdapter',
        'crawl_policy' => ['rate_limit_per_minute' => 1],
    ]);

    $document = SourceDocument::factory()->create([
        'source_id' => $source->id,
        'state' => DocumentState::Discovered,
    ]);

    // Consume the source's single allowed slot for this minute.
    app(SourceRateLimiter::class)->attempt($source, fn () => true);

    FetchSourceDocumentJob::dispatch($document);

    expect($document->fresh()->state)->toBe(DocumentState::Discovered)
        ->and(IngestionFailure::where('source_id', $source->id)->count())->toBe(0);
});

test('DiscoverSourceDocumentsJob releases instead of permanently failing when its own rate limit is hit', function () {
    $source = Source::factory()->create([
        'key' => 'rate_limited_discovery_source',
        'adapter' => 'App\\Domains\\Sources\\Adapters\\FakeSourceAdapter',
        'crawl_policy' => ['rate_limit_per_minute' => 1],
    ]);

    app(SourceRateLimiter::class)->attempt($source, fn () => true);

    DiscoverSourceDocumentsJob::dispatch($source);

    expect(SourceDocument::where('source_id', $source->id)->exists())->toBeFalse()
        ->and(IngestionFailure::where('source_id', $source->id)->count())->toBe(0);
});

test('a genuine fetch failure is still recorded exactly once as an ingestion failure', function () {
    $source = Source::factory()->create([
        'key' => 'genuinely_failing_source',
        'adapter' => 'App\\Domains\\Sources\\Adapters\\FakeSourceAdapter',
    ]);

    FakeSourceAdapter::setSimulateFetchFailure(true);

    $thrown = false;
    try {
        DiscoverSourceDocumentsJob::dispatch($source);
    } catch (Throwable) {
        $thrown = true;
    }

    expect($thrown)->toBeTrue()
        ->and(IngestionFailure::where('source_id', $source->id)->where('stage', 'fetch')->count())->toBe(1);
});
