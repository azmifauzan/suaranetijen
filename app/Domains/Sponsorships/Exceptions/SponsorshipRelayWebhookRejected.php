<?php

namespace App\Domains\Sponsorships\Exceptions;

use RuntimeException;

/**
 * A relayed webhook was well-formed and correctly signed, but its content is permanently
 * unprocessable (unroutable order, correlation mismatch). satsetui must not retry these —
 * they are the "400/401/403/404/422 = non-retryable" bucket in its own forwarding contract.
 *
 * Any other Throwable raised while processing a relay (a DB error, for example) is left
 * unwrapped so it surfaces as a 5xx, which satsetui's retry-with-backoff does redeliver.
 */
class SponsorshipRelayWebhookRejected extends RuntimeException {}
