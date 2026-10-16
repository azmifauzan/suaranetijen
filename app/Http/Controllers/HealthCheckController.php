<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthCheckController extends Controller
{
    /**
     * Probe for external uptime monitors: `/up` only proves the app booted, this
     * also proves the dependencies a request needs. Failure detail stays in the
     * response keys only, never exception text.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->passes(fn () => DB::select('select 1')),
            'redis' => $this->passes(fn () => Redis::connection()->ping()),
        ];

        $healthy = ! in_array(false, $checks, true);

        return response()->json(
            ['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks],
            $healthy ? 200 : 503,
        );
    }

    private function passes(callable $probe): bool
    {
        try {
            $probe();

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
