<?php

use App\Domains\Ingestion\Jobs\DiscoverSourceDocumentsJob;
use App\Domains\Ingestion\Jobs\FetchSourceDocumentJob;
use App\Domains\Sources\Models\CrawlState;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceDocument;
use App\Domains\Sources\Services\SourceRateLimiter;
use App\Domains\Sources\Services\SourceRegistry;
use Illuminate\Support\Facades\Queue;

test('DiscoverSourceDocumentsJob tracks and updates cursor incrementally in crawl_states', function () {
    Queue::fake([FetchSourceDocumentJob::class]);

    $source = Source::factory()->create([
        'key' => 'fake_source',
        'adapter' => 'App\\Domains\\Sources\\Adapters\\FakeSourceAdapter',
    ]);

    // Initial state: no crawl state
    expect(CrawlState::where('source_id', $source->id)->exists())->toBeFalse();

    // 1. First discovery run (page 1 -> next page 2)
    app(DiscoverSourceDocumentsJob::class, ['source' => $source])->handle(
        app(SourceRegistry::class),
        app(SourceRateLimiter::class)
    );

    $state = CrawlState::where('source_id', $source->id)->first();
    expect($state)->not->toBeNull()
        ->and($state->cursor_value)->toBe('page_2')
        ->and($state->last_external_id)->not->toBeNull()
        ->and(SourceDocument::where('source_id', $source->id)->count())->toBe(2);

    // 2. Second discovery run (page 2 -> next page 3)
    app(DiscoverSourceDocumentsJob::class, ['source' => $source])->handle(
        app(SourceRegistry::class),
        app(SourceRateLimiter::class)
    );

    $state->refresh();
    expect($state->cursor_value)->toBe('page_3')
        ->and(SourceDocument::where('source_id', $source->id)->count())->toBe(4);
});
