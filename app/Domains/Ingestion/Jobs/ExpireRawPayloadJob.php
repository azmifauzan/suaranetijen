<?php

namespace App\Domains\Ingestion\Jobs;

use App\Domains\Sources\Services\RawPayloadStorage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireRawPayloadJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Stays under the maintenance supervisor's 60s timeout; a large backlog drains
     * across scheduled runs instead of in one.
     */
    private const MAX_SECONDS = 45;

    public int $uniqueFor = 300;

    public function __construct()
    {
        $this->queue = 'maintenance';
    }

    public function handle(RawPayloadStorage $storage): int
    {
        return $storage->expireExpiredPayloads(maxSeconds: self::MAX_SECONDS);
    }
}
