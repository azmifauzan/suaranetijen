<?php

use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Themes\Jobs\ExtractThemesBatchJob;
use App\Domains\Themes\Jobs\UpsertThemeObservationJob;
use App\Domains\Themes\Services\LlmThemeExtractor;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Queue;

it('dispatches an UpsertThemeObservationJob per theme, matched back to its own item', function () {
    [$entity, $source, , $llmTheme] = themeModeFixture();
    Queue::fake();

    $this->mock(LlmThemeExtractor::class)
        ->shouldReceive('extractBatch')->once()
        ->with($entity->id, 'Samsung', [0 => ['key' => 0, 'text' => 'opini a'], 1 => ['key' => 1, 'text' => 'opini b']])
        ->andReturn([
            1 => [['theme' => $llmTheme, 'sentiment' => SentimentClass::Negative, 'confidence' => 0.8, 'context' => 'Baterai boros.']],
        ]);

    $job = new ExtractThemesBatchJob($entity->id, [
        ['sourceItemId' => 10, 'sourceId' => $source->id, 'sourceDocumentHash' => null, 'publishedAt' => null, 'text' => 'opini a'],
        ['sourceItemId' => 11, 'sourceId' => $source->id, 'sourceDocumentHash' => 'hash', 'publishedAt' => '2026-09-01T00:00:00+00:00', 'text' => 'opini b'],
    ]);
    $job->handle(app(LlmThemeExtractor::class));

    Queue::assertPushed(UpsertThemeObservationJob::class, 1);
    Queue::assertPushed(UpsertThemeObservationJob::class, fn ($j) => $j->sourceItemId === 11
        && $j->themeId === $llmTheme->id && $j->extractor === 'llm' && $j->context === 'Baterai boros.'
        && $j->sourceDocumentHash === 'hash');
});

it('releases the job instead of failing it when the llm answers 429', function () {
    [$entity] = themeModeFixture();
    Queue::fake();

    $response = new Illuminate\Http\Client\Response(new Response(429, ['Retry-After' => '30']));
    $this->mock(LlmThemeExtractor::class)
        ->shouldReceive('extractBatch')->andThrow(new RequestException($response));

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->once()->with(30);

    $job = new ExtractThemesBatchJob($entity->id, [
        ['sourceItemId' => 10, 'sourceId' => 1, 'sourceDocumentHash' => null, 'publishedAt' => null, 'text' => 'x'],
    ]);
    $job->setJob($queueJob);
    $job->handle(app(LlmThemeExtractor::class));

    Queue::assertNothingPushed();
});

it('still fails on non-429 llm errors', function () {
    [$entity] = themeModeFixture();

    $response = new Illuminate\Http\Client\Response(new Response(500));
    $this->mock(LlmThemeExtractor::class)
        ->shouldReceive('extractBatch')->andThrow(new RequestException($response));

    $job = new ExtractThemesBatchJob($entity->id, [
        ['sourceItemId' => 10, 'sourceId' => 1, 'sourceDocumentHash' => null, 'publishedAt' => null, 'text' => 'x'],
    ]);

    expect(fn () => $job->handle(app(LlmThemeExtractor::class)))
        ->toThrow(RequestException::class)
        ->and($job->maxExceptions)->toBe(3)
        ->and($job->retryUntil()->isFuture())->toBeTrue();
});

it('is queued on themes and rate-limited', function () {
    $job = new ExtractThemesBatchJob(1, []);

    expect($job->queue)->toBe('themes')
        ->and($job->middleware())->toHaveCount(1)
        ->and($job->middleware()[0])->toBeInstanceOf(RateLimited::class);
});

it('does nothing for an empty entity name or empty items', function () {
    Queue::fake();
    $this->mock(LlmThemeExtractor::class)->shouldNotReceive('extractBatch');

    (new ExtractThemesBatchJob(999999, [['sourceItemId' => 1, 'sourceId' => 1, 'sourceDocumentHash' => null, 'publishedAt' => null, 'text' => 'x']]))
        ->handle(app(LlmThemeExtractor::class));
    (new ExtractThemesBatchJob(1, []))->handle(app(LlmThemeExtractor::class));

    Queue::assertNothingPushed();
});
