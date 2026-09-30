<?php

use App\Domains\Ingestion\Jobs\PreflightSourceJob;
use App\Domains\Sources\Adapters\AbstractHttpSourceAdapter;
use App\Domains\Sources\Adapters\FakeSourceAdapter;
use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\DiscoveryBatch;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceDocumentRef;
use App\Domains\Sources\Contracts\SourceHealth;
use App\Domains\Sources\Enums\SourceHealthState;
use App\Domains\Sources\Exceptions\RateLimitExceededException;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourcePreflightLog;
use App\Domains\Sources\Services\SourceRegistry;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BusyFlareSolverrFakeAdapter extends FakeSourceAdapter
{
    public function preflight(): SourceHealth
    {
        throw new RateLimitExceededException('flaresolverr', 30);
    }
}

class BrokenFakeAdapter extends FakeSourceAdapter
{
    public function preflight(): SourceHealth
    {
        throw new RuntimeException('connection refused');
    }
}

function preflightSource(string $adapter): Source
{
    return Source::factory()->create(['adapter' => $adapter, 'health_state' => SourceHealthState::Healthy]);
}

it('does not call a source blocked just because the FlareSolverr slot was busy', function () {
    config([
        'services.flaresolverr.url' => 'http://flaresolverr:8191',
        'services.flaresolverr.max_concurrent' => 1,
        'services.flaresolverr.slot_wait_seconds' => 0,
    ]);
    Cache::flush();
    Http::preventStrayRequests();
    $busy = Cache::lock('flaresolverr:slot:'.gethostname().':0', 60);
    $busy->get();

    $adapter = new class extends AbstractHttpSourceAdapter
    {
        protected function preflightUrl(): string
        {
            return 'https://example.test/';
        }

        protected function usesChallengeSolver(): bool
        {
            return true;
        }

        public function discover(CrawlCursor $cursor): DiscoveryBatch
        {
            throw new RuntimeException('not used');
        }

        public function fetch(SourceDocumentRef $ref): FetchedDocument
        {
            throw new RuntimeException('not used');
        }

        public function extract(FetchedDocument $doc): iterable
        {
            return [];
        }
    };

    try {
        $adapter->preflight();
    } finally {
        $busy->release();
    }
})->throws(RateLimitExceededException::class);

it('puts a preflight back on the queue and leaves the health state alone when FlareSolverr is busy', function () {
    $source = preflightSource(BusyFlareSolverrFakeAdapter::class);
    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->once()->with(30);

    $job = new PreflightSourceJob($source);
    $job->setJob($queueJob);
    $job->handle(app(SourceRegistry::class));

    expect($source->fresh()->health_state)->toBe(SourceHealthState::Healthy)
        ->and(SourcePreflightLog::query()->where('source_id', $source->id)->count())->toBe(0);
});

it('still marks a source blocked when its preflight really fails', function () {
    $source = preflightSource(BrokenFakeAdapter::class);

    (new PreflightSourceJob($source))->handle(app(SourceRegistry::class));

    expect($source->fresh()->health_state)->toBe(SourceHealthState::Blocked);
});

it('gives a preflight several attempts so it can wait for a slot', function () {
    $job = new PreflightSourceJob(preflightSource(FakeSourceAdapter::class));

    expect($job->tries)->toBeGreaterThan(1)
        ->and($job->maxExceptions)->toBe(1);
});

it('allows a preflight the time to wait for a slot and still run a full FlareSolverr request', function () {
    // The discovery supervisor kills jobs at 60s; waiting up to slot_wait_seconds (20s) for a slot
    // plus a request of up to max_timeout_ms (45s) does not fit in that (two preflights timed out).
    $job = new PreflightSourceJob(preflightSource(FakeSourceAdapter::class));

    $needed = (int) config('services.flaresolverr.slot_wait_seconds') + (int) ceil((int) config('services.flaresolverr.max_timeout_ms') / 1000);

    expect($job->timeout)->toBeGreaterThan($needed);
});
