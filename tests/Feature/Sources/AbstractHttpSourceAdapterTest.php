<?php

use App\Domains\Sources\Adapters\AbstractHttpSourceAdapter;
use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\DiscoveryBatch;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceDocumentRef;
use App\Domains\Sources\Exceptions\RateLimitExceededException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function testHttpSourceAdapter(): AbstractHttpSourceAdapter
{
    return new class extends AbstractHttpSourceAdapter
    {
        protected function preflightUrl(): string
        {
            return 'https://example.test/';
        }

        public function discover(CrawlCursor $cursor): DiscoveryBatch
        {
            throw new RuntimeException('not used in this test');
        }

        public function fetch(SourceDocumentRef $ref): FetchedDocument
        {
            throw new RuntimeException('not used in this test');
        }

        public function extract(FetchedDocument $doc): iterable
        {
            return [];
        }

        public function callRequest(string $url): Response
        {
            return $this->request($url);
        }

        public function buildPageUrl(string $url, int $page): string
        {
            return $this->pageUrl($url, $page);
        }
    };
}

function testChallengeSolvingAdapter(): AbstractHttpSourceAdapter
{
    return new class extends AbstractHttpSourceAdapter
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
            throw new RuntimeException('not used in this test');
        }

        public function fetch(SourceDocumentRef $ref): FetchedDocument
        {
            throw new RuntimeException('not used in this test');
        }

        public function extract(FetchedDocument $doc): iterable
        {
            return [];
        }

        public function callRequest(string $url): Response
        {
            return $this->request($url);
        }
    };
}

it('preserves a query string already embedded in the URL when no extra query is passed', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        expect($request->url())->toBe('https://example.test/list?page=2');

        return Http::response('ok');
    });

    $adapter = testHttpSourceAdapter();
    $adapter->callRequest($adapter->buildPageUrl('https://example.test/list', 2));
});

it('reuses one FlareSolverr session across multiple requests to the same host', function () {
    config(['services.flaresolverr.url' => 'http://flaresolverr:8191']);

    $sessionCreateCalls = 0;
    Http::preventStrayRequests();
    Http::fake(function (Request $request) use (&$sessionCreateCalls) {
        expect($request->url())->toBe('http://flaresolverr:8191/v1');

        if ($request['cmd'] === 'sessions.destroy') {
            return Http::response(['status' => 'ok']);
        }

        if ($request['cmd'] === 'sessions.create') {
            $sessionCreateCalls++;

            return Http::response(['status' => 'ok']);
        }

        expect($request['cmd'])->toBe('request.get')
            ->and($request['session'])->not->toBeEmpty();

        return Http::response(['status' => 'ok', 'solution' => ['status' => 200, 'response' => 'ok']]);
    });

    $adapter = testChallengeSolvingAdapter();
    $adapter->callRequest('https://example.test/page-1');
    $adapter->callRequest('https://example.test/page-2');

    expect($sessionCreateCalls)->toBe(1);
});

it('recreates the FlareSolverr session and retries once when the session no longer exists', function () {
    config(['services.flaresolverr.url' => 'http://flaresolverr:8191']);

    $requestGetCalls = 0;
    Http::preventStrayRequests();
    Http::fake(function (Request $request) use (&$requestGetCalls) {
        if (in_array($request['cmd'], ['sessions.create', 'sessions.destroy'], true)) {
            return Http::response(['status' => 'ok']);
        }

        $requestGetCalls++;
        if ($requestGetCalls === 1) {
            return Http::response(['status' => 'error', 'message' => 'Error: This session does not exist.']);
        }

        return Http::response(['status' => 'ok', 'solution' => ['status' => 200, 'response' => 'recovered']]);
    });

    $adapter = testChallengeSolvingAdapter();
    $response = $adapter->callRequest('https://example.test/page');

    expect($requestGetCalls)->toBe(2)
        ->and($response->body())->toBe('recovered');
});

function fakeFlareSolverrCommands(array &$commands): void
{
    Http::preventStrayRequests();
    Http::fake(function (Request $request) use (&$commands) {
        $commands[] = $request['cmd'];

        return $request['cmd'] === 'request.get'
            ? Http::response(['status' => 'ok', 'solution' => ['status' => 200, 'response' => 'ok']])
            : Http::response(['status' => 'ok']);
    });
}

it('destroys the FlareSolverr session before creating it, so its browser does not live forever', function () {
    config(['services.flaresolverr.url' => 'http://flaresolverr:8191']);
    Cache::flush();
    $commands = [];
    fakeFlareSolverrCommands($commands);

    testChallengeSolvingAdapter()->callRequest('https://example.test/page-1');

    expect($commands)->toBe(['sessions.destroy', 'sessions.create', 'request.get']);
});

it('rotates the FlareSolverr session once its lifetime has passed and not before', function () {
    config(['services.flaresolverr.url' => 'http://flaresolverr:8191', 'services.flaresolverr.session_ttl_minutes' => 15]);
    Cache::flush();
    $commands = [];
    fakeFlareSolverrCommands($commands);
    $adapter = testChallengeSolvingAdapter();

    $adapter->callRequest('https://example.test/page-1');
    $this->travel(14)->minutes();
    $adapter->callRequest('https://example.test/page-2');
    $this->travel(2)->minutes();
    $adapter->callRequest('https://example.test/page-3');

    expect($commands)->toBe([
        'sessions.destroy', 'sessions.create', 'request.get',
        'request.get',
        'sessions.destroy', 'sessions.create', 'request.get',
    ]);
});

it('keeps the FlareSolverr session bookkeeping per container, since every worker runs its own browser', function () {
    config(['services.flaresolverr.url' => 'http://flaresolverr:8191']);
    Cache::flush();
    $commands = [];
    fakeFlareSolverrCommands($commands);

    testChallengeSolvingAdapter()->callRequest('https://example.test/page-1');

    $sessionId = 'src-'.substr(md5('example.test'), 0, 16);
    expect(Cache::has('flaresolverr:session:'.gethostname().':'.$sessionId))->toBeTrue();
});

it('does not destroy the session when it only has to recreate a missing one', function () {
    config(['services.flaresolverr.url' => 'http://flaresolverr:8191']);
    Cache::flush();
    $commands = [];
    $requestGets = 0;
    Http::preventStrayRequests();
    Http::fake(function (Request $request) use (&$commands, &$requestGets) {
        $commands[] = $request['cmd'];
        if ($request['cmd'] !== 'request.get') {
            return Http::response(['status' => 'ok']);
        }

        return ++$requestGets === 1
            ? Http::response(['status' => 'error', 'message' => 'Error: This session does not exist.'])
            : Http::response(['status' => 'ok', 'solution' => ['status' => 200, 'response' => 'ok']]);
    });

    testChallengeSolvingAdapter()->callRequest('https://example.test/page');

    expect($commands)->toBe(['sessions.destroy', 'sessions.create', 'request.get', 'sessions.create', 'request.get']);
});

function flareSolverrSlotKey(int $slot): string
{
    return 'flaresolverr:slot:'.gethostname().':'.$slot;
}

it('rejects a FlareSolverr request when every browser slot of the container is busy', function () {
    config([
        'services.flaresolverr.url' => 'http://flaresolverr:8191',
        'services.flaresolverr.max_concurrent' => 1,
        'services.flaresolverr.slot_wait_seconds' => 0,
    ]);
    Cache::flush();
    $commands = [];
    fakeFlareSolverrCommands($commands);
    $busy = Cache::lock(flareSolverrSlotKey(0), 60);
    expect($busy->get())->toBeTrue();

    try {
        testChallengeSolvingAdapter()->callRequest('https://example.test/page');
    } finally {
        $busy->release();
    }
})->throws(RateLimitExceededException::class);

it('does not call FlareSolverr at all while every slot is busy', function () {
    config([
        'services.flaresolverr.url' => 'http://flaresolverr:8191',
        'services.flaresolverr.max_concurrent' => 1,
        'services.flaresolverr.slot_wait_seconds' => 0,
    ]);
    Cache::flush();
    $commands = [];
    fakeFlareSolverrCommands($commands);
    $busy = Cache::lock(flareSolverrSlotKey(0), 60);
    $busy->get();

    try {
        testChallengeSolvingAdapter()->callRequest('https://example.test/page');
    } catch (RateLimitExceededException) {
    } finally {
        $busy->release();
    }

    expect($commands)->toBe([]);
});

it('frees the FlareSolverr slot after each request so the next one can use it', function () {
    config(['services.flaresolverr.url' => 'http://flaresolverr:8191', 'services.flaresolverr.max_concurrent' => 1, 'services.flaresolverr.slot_wait_seconds' => 0]);
    Cache::flush();
    $commands = [];
    fakeFlareSolverrCommands($commands);
    $adapter = testChallengeSolvingAdapter();

    $adapter->callRequest('https://example.test/page-1');
    $adapter->callRequest('https://example.test/page-2');

    expect(array_count_values($commands)['request.get'])->toBe(2);
});

it('frees the FlareSolverr slot even when the request blows up', function () {
    config(['services.flaresolverr.url' => 'http://flaresolverr:8191', 'services.flaresolverr.max_concurrent' => 1, 'services.flaresolverr.slot_wait_seconds' => 0]);
    Cache::flush();
    Http::preventStrayRequests();
    Http::fake(fn (Request $request) => $request['cmd'] === 'request.get'
        ? throw new RuntimeException('connection reset')
        : Http::response(['status' => 'ok']));

    try {
        testChallengeSolvingAdapter()->callRequest('https://example.test/page');
    } catch (RuntimeException) {
    }

    $lock = Cache::lock(flareSolverrSlotKey(0), 5);
    expect($lock->get())->toBeTrue();
    $lock->release();
});
