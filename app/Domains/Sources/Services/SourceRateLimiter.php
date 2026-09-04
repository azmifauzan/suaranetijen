<?php

namespace App\Domains\Sources\Services;

use App\Domains\Sources\Exceptions\RateLimitExceededException;
use App\Domains\Sources\Models\Source;
use Illuminate\Support\Facades\RateLimiter;

class SourceRateLimiter
{
    /**
     * Attempt to execute a callback within the source's configured rate limit.
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    public function attempt(Source $source, \Closure $callback): mixed
    {
        $rateLimitPerMinute = (int) ($source->crawl_policy['rate_limit_per_minute'] ?? 60);
        $limiterKey = 'source_crawl_limiter:'.$source->id;

        $executed = RateLimiter::attempt(
            $limiterKey,
            $rateLimitPerMinute,
            $callback,
            60
        );

        if ($executed === false) {
            throw new RateLimitExceededException($source->key, RateLimiter::availableIn($limiterKey));
        }

        return $executed;
    }

    /**
     * Clear the rate limiter for a source.
     */
    public function clear(Source $source): void
    {
        RateLimiter::clear('source_crawl_limiter:'.$source->id);
    }
}
