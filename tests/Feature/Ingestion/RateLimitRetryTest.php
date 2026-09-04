<?php

use App\Domains\Ingestion\Jobs\DiscoverSourceDocumentsJob;
use App\Domains\Ingestion\Jobs\FetchSourceDocumentJob;
use App\Domains\Sources\Adapters\FakeSourceAdapter;
use App\Domains\Sources\Enums\DocumentState;
use App\Domains\Sources\Exceptions\RateLimitExceededException;
use App\Domains\Sources\Models\IngestionFailure;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceDocument;
use App\Domains\Sources\Services\SourceRateLimiter;

beforeEach(function () {
    FakeSourceAdapter::reset();
});

test('FetchSourceDocumentJob recovers and succeeds once its own rate limit clears', function () {
    $source = Source::factory()->create([
        'key' => 'rate_limited_fetch_source',
        'adapter' => 'App\\Domains\\Sources\\Adapters\\FakeSourceAdapter',
    ]);

    $document = SourceDocument::factory()->create([
        'source_id' => $source->id,
        'state' => DocumentState::Discovered,
    ]);

    $limiter = Mockery::mock(SourceRateLimiter::class);
    $limiter->shouldReceive('attempt')->once()
        ->andThrow(new RateLimitExceededException($source->key, 5));
    $limiter->shouldReceive('attempt')->once()
        ->andReturnUsing(fn ($_, Closure $callback) => $callback());
    app()->instance(SourceRateLimiter::class, $limiter);

    FetchSourceDocumentJob::dispatch($document);

    expect($document->fresh()->state)->toBe(DocumentState::Fetched)
        ->and(IngestionFailure::where('source_id', $source->id)->count())->toBe(0);
});

test('FetchSourceDocumentJob gives up and records exactly one failure once rate-limit bounces are exhausted', function () {
    $source = Source::factory()->create([
        'key' => 'permanently_rate_limited_fetch_source',
        'adapter' => 'App\\Domains\\Sources\\Adapters\\FakeSourceAdapter',
        'crawl_policy' => ['rate_limit_per_minute' => 1],
    ]);

    $document = SourceDocument::factory()->create([
        'source_id' => $source->id,
        'state' => DocumentState::Discovered,
    ]);

    // Consume the source's single allowed slot; it never clears within the test.
    app(SourceRateLimiter::class)->attempt($source, fn () => true);

    FetchSourceDocumentJob::dispatch($document);

    expect($document->fresh()->state)->toBe(DocumentState::Failed)
        ->and(IngestionFailure::where('source_id', $source->id)->count())->toBe(1);
});

test('DiscoverSourceDocumentsJob recovers and succeeds once its own rate limit clears', function () {
    $source = Source::factory()->create([
        'key' => 'rate_limited_discovery_source',
        'adapter' => 'App\\Domains\\Sources\\Adapters\\FakeSourceAdapter',
    ]);

    $limiter = Mockery::mock(SourceRateLimiter::class);
    $limiter->shouldReceive('attempt')->once()
        ->andThrow(new RateLimitExceededException($source->key, 5));
    // Unbounded: the discover retry, plus the fetch job it cascades into for
    // each discovered document, all resolve this same mocked limiter.
    $limiter->shouldReceive('attempt')
        ->andReturnUsing(fn ($_, Closure $callback) => $callback());
    app()->instance(SourceRateLimiter::class, $limiter);

    DiscoverSourceDocumentsJob::dispatch($source);

    expect(SourceDocument::where('source_id', $source->id)->count())->toBe(2)
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
