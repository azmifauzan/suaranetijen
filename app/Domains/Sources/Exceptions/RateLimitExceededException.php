<?php

namespace App\Domains\Sources\Exceptions;

use RuntimeException;

class RateLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $sourceKey,
        public readonly int $retryAfterSeconds
    ) {
        parent::__construct("Rate limit exceeded for source [{$sourceKey}]. Try again in {$retryAfterSeconds} seconds.");
    }
}
