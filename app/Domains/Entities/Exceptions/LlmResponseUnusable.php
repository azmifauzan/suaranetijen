<?php

namespace App\Domains\Entities\Exceptions;

use Illuminate\Http\Client\HttpClientException;

/**
 * The provider answered 200 but the completion cannot be used: cut off at max_tokens (a reasoning
 * model spent the budget thinking) or not valid JSON. Callers must treat it like a failed call,
 * never as "the model found nothing".
 */
class LlmResponseUnusable extends HttpClientException {}
