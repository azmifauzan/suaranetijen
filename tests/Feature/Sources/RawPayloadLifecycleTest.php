<?php

use App\Domains\Ingestion\Jobs\ExpireRawPayloadJob;
use App\Domains\Sources\Enums\ProcessingState;
use App\Domains\Sources\Models\RawPayload;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Services\RawPayloadStorage;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;

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

    // 3. Fast-forward time past TTL (25 hours later); the item has been processed by then
    $item->update(['processing_state' => ProcessingState::Processed]);
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

function expiredPayloadFor(ProcessingState $state, int $expiredDaysAgo = 1): array
{
    $source = Source::factory()->create();
    $item = SourceItem::factory()->create(['source_id' => $source->id, 'processing_state' => $state]);
    $payload = app(RawPayloadStorage::class)->store($source, 'Teks opini sementara.', $item, 'text/plain');
    $payload->update(['expires_at' => CarbonImmutable::now()->subDays($expiredDaysAgo)]);

    return [$payload, $item];
}

test('prune deletes expired payloads only for processed or skipped items and clears their ref', function () {
    [$processed, $processedItem] = expiredPayloadFor(ProcessingState::Processed);
    [$skipped] = expiredPayloadFor(ProcessingState::Skipped);
    [$pending, $pendingItem] = expiredPayloadFor(ProcessingState::Pending);
    [$failed] = expiredPayloadFor(ProcessingState::Failed);

    $deleted = app(RawPayloadStorage::class)->expireExpiredPayloads();

    expect($deleted)->toBe(2)
        ->and(RawPayload::whereKey($processed->id)->exists())->toBeFalse()
        ->and(RawPayload::whereKey($skipped->id)->exists())->toBeFalse()
        ->and(RawPayload::whereKey($pending->id)->exists())->toBeTrue()
        ->and(RawPayload::whereKey($failed->id)->exists())->toBeTrue()
        ->and($processedItem->fresh()->raw_payload_ref)->toBeNull()
        ->and($pendingItem->fresh()->raw_payload_ref)->toBe($pending->payload_ref);
});

test('prune never deletes a payload that has not expired yet, even if its item is processed', function () {
    $source = Source::factory()->create();
    $item = SourceItem::factory()->create(['source_id' => $source->id, 'processing_state' => ProcessingState::Processed]);
    $payload = app(RawPayloadStorage::class)->store($source, 'Masih berlaku.', $item, 'text/plain');

    expect(app(RawPayloadStorage::class)->expireExpiredPayloads())->toBe(0)
        ->and(RawPayload::whereKey($payload->id)->exists())->toBeTrue();
});

test('prune deletes an unprocessed payload once it is past the grace window', function () {
    [$stale] = expiredPayloadFor(ProcessingState::Failed, RawPayloadStorage::UNPROCESSED_GRACE_DAYS + 1);
    [$recent] = expiredPayloadFor(ProcessingState::Failed, 1);

    app(RawPayloadStorage::class)->expireExpiredPayloads();

    expect(RawPayload::whereKey($stale->id)->exists())->toBeFalse()
        ->and(RawPayload::whereKey($recent->id)->exists())->toBeTrue();
});

test('prune deletes expired payloads that have no item at all', function () {
    $source = Source::factory()->create();
    $orphan = app(RawPayloadStorage::class)->store($source, 'Tanpa item.', null, 'text/plain');
    $orphan->update(['expires_at' => CarbonImmutable::now()->subHour()]);

    expect(app(RawPayloadStorage::class)->expireExpiredPayloads())->toBe(1);
});

test('expire job is unique and scheduled every five minutes', function () {
    $scheduled = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->description, 'ExpireRawPayloadJob'));

    expect(new ExpireRawPayloadJob)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($scheduled)->not->toBeNull()
        ->and($scheduled->expression)->toBe('*/5 * * * *');
});

test('prune only clears the ref of an item that still points at the deleted payload', function () {
    [$payload, $item] = expiredPayloadFor(ProcessingState::Processed);
    $item->update(['raw_payload_ref' => 'payload-newer-ref']);

    app(RawPayloadStorage::class)->expireExpiredPayloads();

    expect(RawPayload::whereKey($payload->id)->exists())->toBeFalse()
        ->and($item->fresh()->raw_payload_ref)->toBe('payload-newer-ref');
});
