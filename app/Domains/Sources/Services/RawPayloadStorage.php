<?php

namespace App\Domains\Sources\Services;

use App\Domains\Sources\Models\RawPayload;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class RawPayloadStorage
{
    public const DEFAULT_TTL_HOURS = 72;

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
     * Expire all payloads whose TTL has ended per docs/06 raw content policy.
     * Clears raw_payload_ref while preserving external_id and content_hash on source_items.
     */
    public function expireExpiredPayloads(?CarbonImmutable $now = null): int
    {
        $referenceNow = $now ?? CarbonImmutable::now();

        // Nullify raw_payload_ref on source_items that are expiring
        SourceItem::query()
            ->whereNotNull('raw_payload_ref')
            ->where('expires_at', '<=', $referenceNow)
            ->update(['raw_payload_ref' => null]);

        // Delete expired raw payloads
        return RawPayload::query()
            ->where('expires_at', '<=', $referenceNow)
            ->delete();
    }
}
