<?php

use App\Domains\Ingestion\Jobs\ExpireRawPayloadJob;
use App\Domains\Sources\Models\RawPayload;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Services\RawPayloadStorage;
use Carbon\CarbonImmutable;

test('raw payload is stored with per-source TTL and expired by ExpireRawPayloadJob', function () {
    $source = Source::factory()->create([
        'retention_policy' => ['raw_ttl_hours' => 24],
    ]);

    $item = SourceItem::factory()->create([
        'source_id' => $source->id,
        'external_id' => 'comment-12345',
        'content_hash' => hash('sha256', 'Komentar netijen untuk diteliti'),
    ]);

    $storage = app(RawPayloadStorage::class);

    // 1. Store raw payload
    $rawPayload = $storage->store($source, '<html><body>Komentar netijen untuk diteliti</body></html>', $item);

    expect($rawPayload->expires_at->toIso8601String())
        ->toBe(CarbonImmutable::now()->addHours(24)->toIso8601String())
        ->and($item->fresh()->raw_payload_ref)->toBe($rawPayload->payload_ref);

    // 2. Running expire job before expiry retains the payload
    $deleted = app(ExpireRawPayloadJob::class)->handle($storage);
    expect($deleted)->toBe(0)
        ->and(RawPayload::where('id', $rawPayload->id)->exists())->toBeTrue()
        ->and($item->fresh()->raw_payload_ref)->not->toBeNull();

    // 3. Fast-forward time past TTL (25 hours later)
    $future = CarbonImmutable::now()->addHours(25);
    $storage->expireExpiredPayloads($future);

    // Verify raw payload was deleted
    expect(RawPayload::where('id', $rawPayload->id)->exists())->toBeFalse();

    // Verify source_item raw_payload_ref was cleared to null
    $freshItem = $item->fresh();
    expect($freshItem->raw_payload_ref)->toBeNull();

    // Verify content_hash and external_id persist for deduplication per docs/06
    expect($freshItem->external_id)->toBe('comment-12345')
        ->and($freshItem->content_hash)->toBe(hash('sha256', 'Komentar netijen untuk diteliti'));
});
