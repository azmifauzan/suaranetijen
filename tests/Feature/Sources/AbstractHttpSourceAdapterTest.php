<?php

use App\Domains\Sources\Adapters\AbstractHttpSourceAdapter;
use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\DiscoveryBatch;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceDocumentRef;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
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
        if ($request['cmd'] === 'sessions.create') {
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
