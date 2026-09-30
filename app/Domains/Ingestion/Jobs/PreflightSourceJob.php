<?php

namespace App\Domains\Ingestion\Jobs;

use App\Domains\Sources\Enums\SourceHealthState;
use App\Domains\Sources\Exceptions\RateLimitExceededException;
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

    /**
     * Attempts to wait for a free FlareSolverr slot: every source preflights at once, but a
     * container renders one page at a time. maxExceptions keeps a real failure to one throw.
     */
    public int $tries = 6;

    public int $maxExceptions = 1;

    /**
     * Above the discovery supervisor's 60s: a slot wait (up to 20s) plus a FlareSolverr request
     * (up to 45s) otherwise gets the job killed, leaving the source's health state stale.
     */
    public int $timeout = 90;

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
        } catch (RateLimitExceededException $e) {
            // The container was busy or cooling down, so nothing was learned about the source:
            // try again later and keep its current health state (blocked would stop its crawl).
            $this->release(max(10, $e->retryAfterSeconds));
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
