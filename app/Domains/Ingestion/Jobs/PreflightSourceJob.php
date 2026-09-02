<?php

namespace App\Domains\Ingestion\Jobs;

use App\Domains\Sources\Enums\SourceHealthState;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourcePreflightLog;
use App\Domains\Sources\Services\SourceRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PreflightSourceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Source $source
    ) {
        $this->queue = 'discovery';
    }

    public function handle(SourceRegistry $registry): void
    {
        $startTime = microtime(true);

        try {
            $adapter = $registry->resolve($this->source);
            $health = $adapter->preflight();
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            $this->source->update([
                'health_state' => $health->status,
                'last_preflight_at' => CarbonImmutable::now(),
            ]);

            SourcePreflightLog::create([
                'source_id' => $this->source->id,
                'status' => $health->status,
                'response_time_ms' => $health->responseTimeMs ?? $durationMs,
                'message' => $health->message,
                'details' => $health->details,
            ]);
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            $this->source->update([
                'health_state' => SourceHealthState::Blocked,
                'last_preflight_at' => CarbonImmutable::now(),
            ]);

            SourcePreflightLog::create([
                'source_id' => $this->source->id,
                'status' => SourceHealthState::Blocked,
                'response_time_ms' => $durationMs,
                'message' => $e->getMessage(),
                'details' => ['exception' => get_class($e)],
            ]);
        }
    }
}
