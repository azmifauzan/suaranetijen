<?php

use App\Domains\Entities\Exceptions\CodexUnavailable;
use App\Domains\Entities\Services\LlmClient;
use App\Domains\Themes\Jobs\Middleware\CodexBeforeApiFallback;

function fakeQueuedJob(int $maxExceptions): object
{
    $job = new class
    {
        public int $maxExceptions = 3;

        public ?object $job = null;
    };
    $job->maxExceptions = $maxExceptions;
    $job->job = new class
    {
        public function uuid(): string
        {
            return 'job-uuid-1';
        }
    };

    return $job;
}

it('defers the API fallback until the last allowed exception', function () {
    $job = fakeQueuedJob(3);
    $seen = [];
    $attempt = function () use ($job, &$seen): void {
        try {
            (new CodexBeforeApiFallback)->handle($job, function () use (&$seen) {
                $seen[] = LlmClient::$deferApiFallback;

                if (LlmClient::$deferApiFallback) {
                    throw new CodexUnavailable(new RuntimeException('504'));
                }

                return null;
            });
        } catch (CodexUnavailable) {
        }
    };

    $attempt();
    $attempt();
    $attempt();

    expect($seen)->toBe([true, true, false])
        ->and(LlmClient::$deferApiFallback)->toBeFalse();
});

it('does not defer outside the queue', function () {
    $seen = null;
    (new CodexBeforeApiFallback)->handle(new class
    {
        public ?object $job = null;
    }, function () use (&$seen) {
        $seen = LlmClient::$deferApiFallback;
    });

    expect($seen)->toBeFalse();
});
