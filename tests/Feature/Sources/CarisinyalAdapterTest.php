<?php

use App\Domains\Sources\Adapters\CarisinyalAdapter;
use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\SourceAdapter;
use App\Domains\Sources\Contracts\SourceDocumentRef;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Services\SourceRegistry;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function carisinyalFixture(string $file): string
{
    return (string) file_get_contents(base_path("tests/Fixtures/Sources/carisinyal/{$file}"));
}

it('runs the Carisinyal adapter through preflight, feed discovery, fetch, and comment extraction', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match (true) {
            $request->url() === 'https://carisinyal.com/' => Http::response('ok'),
            $request->url() === 'https://carisinyal.com/feed' => Http::response(carisinyalFixture('feed.xml')),
            str_contains($request->url(), '/cara-memilih-hp') => Http::response(carisinyalFixture('article.html')),
            default => throw new RuntimeException("Unexpected Carisinyal request [{$request->url()}]."),
        };
    });

    $adapter = new CarisinyalAdapter;
    $batch = $adapter->discover(CrawlCursor::initial('carisinyal'));
    $opinions = iterator_to_array($adapter->extract($adapter->fetch($batch->documents[0])));

    expect($adapter->preflight()->isHealthy())->toBeTrue()
        ->and($batch->documents)->toHaveCount(2)
        ->and($batch->documents[0]->externalId)->toBe('https://carisinyal.com/?p=300001')
        ->and($opinions)->toHaveCount(2)
        ->and($opinions[0]->text)->toContain('budgetku 2,9 jt')
        ->and($opinions[1]->text)->toContain('sesuai kebutuhan')
        ->and($opinions[0]->text)->not->toContain('Hajar Mutiana')
        ->and($opinions[1]->text)->not->toContain('Hilman Mulya Nugraha');
});

it('returns no opinions for an article with no comments', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match (true) {
            str_contains($request->url(), '/harga-hp-contoh-merek') => Http::response(carisinyalFixture('article_no_comments.html')),
            default => throw new RuntimeException("Unexpected Carisinyal request [{$request->url()}]."),
        };
    });

    $adapter = new CarisinyalAdapter;
    $ref = new SourceDocumentRef(
        sourceKey: 'carisinyal',
        externalId: 'https://carisinyal.com/?p=300002',
        canonicalUrl: 'https://carisinyal.com/harga-hp-contoh-merek/'
    );

    $opinions = iterator_to_array($adapter->extract($adapter->fetch($ref)));

    expect($opinions)->toBe([]);
});

it('paginates the Carisinyal feed with paged= instead of page=, which WordPress ignores for feeds', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        if ($request->url() === 'https://carisinyal.com/feed?paged=2') {
            return Http::response(carisinyalFixture('feed.xml'));
        }

        throw new RuntimeException("Unexpected Carisinyal request [{$request->url()}].");
    });

    $adapter = new CarisinyalAdapter;
    $batch = $adapter->discover(new CrawlCursor('carisinyal', metadata: ['page' => 2]));

    expect($batch->documents)->toHaveCount(2);
});

it('resolves the Carisinyal adapter through the source registry', function () {
    $registry = app(SourceRegistry::class);
    $source = new Source(['key' => 'carisinyal', 'adapter' => 'carisinyal']);

    expect($registry->resolve($source))->toBeInstanceOf(CarisinyalAdapter::class)
        ->and($registry->resolve($source))->toBeInstanceOf(SourceAdapter::class);
});
