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

it('preserves a query string already embedded in the URL when no extra query is passed', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        expect($request->url())->toBe('https://example.test/list?page=2');

        return Http::response('ok');
    });

    $adapter = testHttpSourceAdapter();
    $adapter->callRequest($adapter->buildPageUrl('https://example.test/list', 2));
});
