<?php

namespace App\Domains\Ingestion\Jobs;

use App\Domains\Sources\Services\RawPayloadStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireRawPayloadJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->queue = 'maintenance';
    }

    public function handle(RawPayloadStorage $storage): int
    {
        return $storage->expireExpiredPayloads();
    }
}
