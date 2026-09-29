<?php

namespace App\Domains\Sources\Services;

use App\Domains\Sources\Enums\ProcessingState;
use App\Domains\Sources\Models\RawPayload;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class RawPayloadStorage
{
    public const DEFAULT_TTL_HOURS = 72;

    /**
     * An expired payload whose item is still pending/failed is kept for replay, but
     * never longer than this many days past expiry (docs/06: raw text must not persist).
     */
    public const UNPROCESSED_GRACE_DAYS = 14;

    /**
     * Small enough that one chunk (large rows, TOAST deletes) finishes in seconds, so the
     * time budget below is honoured instead of a single chunk overrunning the job timeout.
     */
    private const PRUNE_CHUNK = 500;

    private const PRUNE_PAUSE_MICROSECONDS = 100_000;

    /**
     * Store a temporary raw payload for a source with configured TTL.
     */
    public function store(
        Source $source,
        string $payload,
        ?SourceItem $item = null,
        string $contentType = 'text/html'
    ): RawPayload {
        $ttlHours = (int) ($source->retention_policy['raw_ttl_hours'] ?? self::DEFAULT_TTL_HOURS);
        $expiresAt = CarbonImmutable::now()->addHours($ttlHours);
        $payloadRef = 'payload-'.Str::uuid()->toString();

        $rawPayload = RawPayload::create([
            'source_id' => $source->id,
            'source_item_id' => $item?->id,
            'payload_ref' => $payloadRef,
            'payload' => $payload,
            'content_type' => $contentType,
            'expires_at' => $expiresAt,
        ]);

        if ($item !== null) {
            $item->update([
                'raw_payload_ref' => $payloadRef,
                'expires_at' => $expiresAt,
            ]);
        }

        return $rawPayload;
    }

    /**
     * Delete expired raw payloads per docs/06, only once their item has been processed
     * (or skipped) — or after UNPROCESSED_GRACE_DAYS. Clears raw_payload_ref on the
     * items whose payload was deleted, keeping external_id and content_hash for dedup.
     * Deletes in small chunks and stops after $maxSeconds so a large backlog never holds
     * long locks or blows a queue timeout; the next run continues where this one stopped.
     */
    public function expireExpiredPayloads(?CarbonImmutable $now = null, ?int $maxSeconds = null): int
    {
        $referenceNow = $now ?? CarbonImmutable::now();
        $graceCutoff = $referenceNow->subDays(self::UNPROCESSED_GRACE_DAYS);
        $deadline = $maxSeconds === null ? null : microtime(true) + $maxSeconds;
        $deleted = 0;
        $chunkSeconds = 0.0;

        do {
            $ids = RawPayload::query()
                ->where('expires_at', '<=', $referenceNow)
                ->where(fn ($query) => $query
                    ->whereNull('source_item_id')
                    ->orWhere('expires_at', '<=', $graceCutoff)
                    ->orWhereHas('item', fn ($item) => $item->whereIn('processing_state', [
                        ProcessingState::Processed->value,
                        ProcessingState::Skipped->value,
                    ])))
                ->orderBy('id')
                ->limit(self::PRUNE_CHUNK)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $chunkStartedAt = microtime(true);

            // By primary key (item ids taken from the payloads) rather than a subquery on
            // raw_payload_ref, which cost seconds per chunk on a million-row table.
            $links = RawPayload::query()->whereIn('id', $ids)->whereNotNull('source_item_id')->pluck('payload_ref', 'source_item_id');
            if ($links->isNotEmpty()) {
                SourceItem::query()
                    ->whereIn('id', $links->keys())
                    ->whereIn('raw_payload_ref', $links->values())
                    ->update(['raw_payload_ref' => null]);
            }

            $deleted += RawPayload::query()->whereIn('id', $ids)->delete();

            usleep(self::PRUNE_PAUSE_MICROSECONDS);
            $chunkSeconds = microtime(true) - $chunkStartedAt;
        } while ($ids->count() === self::PRUNE_CHUNK
            && ($deadline === null || microtime(true) + $chunkSeconds * 1.5 < $deadline));

        return $deleted;
    }
}
