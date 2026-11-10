<?php

namespace App\Domains\Entities\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The Codex runner failed while the queue job still has retries left, so the paid API
 * fallback was held back. The job fails this attempt and tries Codex again on retry.
 */
class CodexUnavailable extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('Codex runner failed; API fallback deferred to the final attempt: '.$previous->getMessage(), 0, $previous);
    }
}
