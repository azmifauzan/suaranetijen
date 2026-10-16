<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Themes\Jobs\ExtractThemesBatchJob;
use App\Domains\Themes\Jobs\SummarizeEntityThemesJob;
use App\Domains\Themes\Services\EntityThemeSummarizer;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\Client\RequestException;

it('releases a summary on a runner rate limit without spending its exception budget', function () {
    $entity = Entity::factory()->create();
    $exception = new RequestException(new Illuminate\Http\Client\Response(new Response(429, ['Retry-After' => '300'])));
    $summarizer = Mockery::mock(EntityThemeSummarizer::class);
    $summarizer->shouldReceive('summarize')->once()->andThrow($exception);
    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->once()->with(300);
    $job = new SummarizeEntityThemesJob($entity->id);
    $job->setJob($queueJob);

    $job->handle($summarizer);

    expect($job->maxExceptions)->toBe(3);
    expect($job->retryUntil()->isFuture())->toBeTrue();
});

it('surfaces summary authentication and runtime failures', function (int $status) {
    $entity = Entity::factory()->create();
    $exception = new RequestException(new Illuminate\Http\Client\Response(new Response($status)));
    $summarizer = Mockery::mock(EntityThemeSummarizer::class);
    $summarizer->shouldReceive('summarize')->once()->andThrow($exception);

    expect(fn () => (new SummarizeEntityThemesJob($entity->id))->handle($summarizer))->toThrow(RequestException::class);
})->with([401, 504]);

it('keeps codex HTTP and job timeouts below worker and reservation limits', function () {
    $job = new SummarizeEntityThemesJob(1);
    $batch = new ExtractThemesBatchJob(1, []);
    foreach (['supervisor-critical', 'supervisor-themes'] as $supervisor) {
        $workerTimeout = config("horizon.defaults.{$supervisor}.timeout");
        expect($workerTimeout)->toBeGreaterThan(max($job->timeout, $batch->timeout));
        expect(config('queue.connections.redis.retry_after'))->toBeGreaterThan($workerTimeout);
    }
});
