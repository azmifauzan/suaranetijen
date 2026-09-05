<?php

use App\Domains\Sources\Adapters\MojokAdapter;
use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\SourceAdapter;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Services\SourceRegistry;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function mojokFixture(string $file): string
{
    return (string) file_get_contents(base_path("tests/Fixtures/Sources/mojok/{$file}"));
}

it('runs the Mojok adapter through preflight, feed discovery, fetch, and extraction', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match (true) {
            $request->url() === 'https://mojok.co/' => Http::response('ok'),
            $request->url() === 'https://mojok.co/esai/feed' => Http::response(mojokFixture('feed.xml')),
            str_contains($request->url(), '/esai/contoh-produk-bikin-kesal-layanan-purnajual') => Http::response(mojokFixture('article.html')),
            default => throw new RuntimeException("Unexpected Mojok request [{$request->url()}]."),
        };
    });

    $adapter = new MojokAdapter;
    $batch = $adapter->discover(CrawlCursor::initial('mojok'));
    $opinions = iterator_to_array($adapter->extract($adapter->fetch($batch->documents[0])));

    expect($adapter->preflight()->isHealthy())->toBeTrue()
        ->and($batch->documents)->toHaveCount(2)
        ->and($batch->documents[0]->externalId)->toBe('https://mojok.co/?p=200001')
        ->and($batch->documents[1]->externalId)->toBe('https://mojok.co/?p=200002')
        ->and($opinions)->toHaveCount(1)
        ->and($opinions[0]->text)->toContain('lambat')
        ->and($opinions[0]->text)->not->toContain('adsbygoogle')
        ->and($opinions[0]->text)->not->toContain('menerima kiriman esai');
});

it('paginates the Mojok feed with paged= instead of page=, which WordPress ignores for feeds', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        if ($request->url() === 'https://mojok.co/esai/feed?paged=2') {
            return Http::response(mojokFixture('feed.xml'));
        }

        throw new RuntimeException("Unexpected Mojok request [{$request->url()}].");
    });

    $adapter = new MojokAdapter;
    $batch = $adapter->discover(new CrawlCursor('mojok', metadata: ['page' => 2]));

    expect($batch->documents)->toHaveCount(2);
});

it('resolves the Mojok adapter through the source registry', function () {
    $registry = app(SourceRegistry::class);
    $source = new Source(['key' => 'mojok', 'adapter' => 'mojok']);

    expect($registry->resolve($source))->toBeInstanceOf(MojokAdapter::class)
        ->and($registry->resolve($source))->toBeInstanceOf(SourceAdapter::class);
});
