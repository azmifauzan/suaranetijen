<?php

namespace App\Domains\Themes\Jobs\Middleware;

use App\Domains\Entities\Exceptions\CodexUnavailable;
use App\Domains\Entities\Services\LlmClient;
use Closure;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps a queued LLM job on the Codex runner until its last allowed exception, so the paid
 * API fallback only runs once Codex has failed on every earlier attempt.
 */
class CodexBeforeApiFallback
{
    /**
     * @param  object{job: Job|null, maxExceptions: int}  $job
     */
    public function handle(object $job, Closure $next): mixed
    {
        $uuid = $job->job?->uuid();

        if ($uuid === null) {
            return $next($job);
        }

        $key = "llm:codex-failures:{$uuid}";
        $deferFallback = (int) Cache::get($key, 0) < max(1, $job->maxExceptions) - 1;

        LlmClient::$deferApiFallback = $deferFallback;

        try {
            return $next($job);
        } catch (CodexUnavailable $e) {
            Cache::add($key, 0, now()->addDay());
            Cache::increment($key);

            throw $e;
        } finally {
            LlmClient::$deferApiFallback = false;
        }
    }
}
