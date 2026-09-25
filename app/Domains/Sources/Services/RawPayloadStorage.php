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

    private const PRUNE_CHUNK = 2000;

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

            SourceItem::query()
                ->whereIn('raw_payload_ref', RawPayload::query()->whereIn('id', $ids)->select('payload_ref'))
                ->update(['raw_payload_ref' => null]);

            $deleted += RawPayload::query()->whereIn('id', $ids)->delete();

            usleep(self::PRUNE_PAUSE_MICROSECONDS);
        } while ($ids->count() === self::PRUNE_CHUNK && ($deadline === null || microtime(true) < $deadline));

        return $deleted;
    }
}
