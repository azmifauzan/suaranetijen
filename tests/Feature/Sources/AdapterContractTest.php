<?php

use App\Domains\Sources\Adapters\FakeSourceAdapter;
use App\Domains\Sources\Contracts\CandidateOpinion;
use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\DiscoveryBatch;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceAdapter;
use App\Domains\Sources\Contracts\SourceDocumentRef;
use App\Domains\Sources\Contracts\SourceHealth;
use App\Domains\Sources\Enums\SourceHealthState;

beforeEach(function () {
    FakeSourceAdapter::reset();
});

test('FakeSourceAdapter adheres to SourceAdapter contract', function () {
    $adapter = new FakeSourceAdapter;
    expect($adapter)->toBeInstanceOf(SourceAdapter::class);

    // 1. Preflight
    $health = $adapter->preflight();
    expect($health)->toBeInstanceOf(SourceHealth::class)
        ->and($health->status)->toBe(SourceHealthState::Healthy)
        ->and($health->isHealthy())->toBeTrue();

    // 2. Discover
    $cursor = CrawlCursor::initial('diskusiwebhosting');
    $batch = $adapter->discover($cursor);

    expect($batch)->toBeInstanceOf(DiscoveryBatch::class)
        ->and($batch->documents)->not->toBeEmpty()
        ->and($batch->documents[0])->toBeInstanceOf(SourceDocumentRef::class)
        ->and($batch->nextCursor)->not->toBeNull()
        ->and($batch->nextCursor->cursorValue)->toBe('page_2');

    // 3. Fetch
    $fetched = $adapter->fetch($batch->documents[0]);
    expect($fetched)->toBeInstanceOf(FetchedDocument::class)
        ->and($fetched->rawPayload)->toContain('VPS Biznet Gio')
        ->and($fetched->contentType)->toBe('text/html');

    // 4. Extract
    $opinions = iterator_to_array($adapter->extract($fetched));
    expect($opinions)->not->toBeEmpty()
        ->and($opinions[0])->toBeInstanceOf(CandidateOpinion::class)
        ->and($opinions[0]->getContentHash())->toHaveLength(64);
});
