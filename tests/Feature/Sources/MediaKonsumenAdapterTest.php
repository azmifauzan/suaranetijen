<?php

use App\Domains\Sources\Adapters\MediaKonsumenAdapter;
use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\SourceAdapter;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Services\SourceRegistry;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function mediaKonsumenFixture(string $file): string
{
    return (string) file_get_contents(base_path("tests/Fixtures/Sources/mediakonsumen/{$file}"));
}

it('runs the MediaKonsumen adapter through preflight, feed discovery, fetch, and extraction', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match (true) {
            $request->url() === 'https://mediakonsumen.com/' => Http::response('ok'),
            $request->url() === 'https://mediakonsumen.com/feed' => Http::response(mediaKonsumenFixture('feed.xml')),
            str_contains($request->url(), '/surat-pembaca/layanan-internet-contoh-provider-lambat') => Http::response(mediaKonsumenFixture('article.html')),
            default => throw new RuntimeException("Unexpected MediaKonsumen request [{$request->url()}]."),
        };
    });

    $adapter = new MediaKonsumenAdapter;
    $batch = $adapter->discover(CrawlCursor::initial('mediakonsumen'));
    $opinions = iterator_to_array($adapter->extract($adapter->fetch($batch->documents[0])));

    expect($adapter->preflight()->isHealthy())->toBeTrue()
        ->and($batch->documents)->toHaveCount(2)
        ->and($batch->documents[0]->externalId)->toBe('https://mediakonsumen.com/?p=100001')
        ->and($batch->documents[1]->externalId)->toBe('https://mediakonsumen.com/?p=100002')
        ->and($opinions)->toHaveCount(1)
        ->and($opinions[0]->text)->toContain('lambat')
        ->and($opinions[0]->text)->not->toContain('adsbygoogle')
        ->and($opinions[0]->text)->not->toContain('buatan pengguna');
});

it('paginates the MediaKonsumen feed with paged= instead of page=, which WordPress ignores for feeds', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        if ($request->url() === 'https://mediakonsumen.com/feed?paged=2') {
            return Http::response(mediaKonsumenFixture('feed.xml'));
        }

        throw new RuntimeException("Unexpected MediaKonsumen request [{$request->url()}].");
    });

    $adapter = new MediaKonsumenAdapter;
    $batch = $adapter->discover(new CrawlCursor('mediakonsumen', metadata: ['page' => 2]));

    expect($batch->documents)->toHaveCount(2);
});

it('resolves the MediaKonsumen adapter through the source registry', function () {
    $registry = app(SourceRegistry::class);
    $source = new Source(['key' => 'mediakonsumen', 'adapter' => 'mediakonsumen']);

    expect($registry->resolve($source))->toBeInstanceOf(MediaKonsumenAdapter::class)
        ->and($registry->resolve($source))->toBeInstanceOf(SourceAdapter::class);
});
