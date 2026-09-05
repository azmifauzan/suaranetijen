<?php

use App\Domains\Sources\Adapters\KaskusAdapter;
use App\Domains\Sources\Adapters\LowEndTalkAdapter;
use App\Domains\Sources\Adapters\YouTubeAdapter;
use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\SourceAdapter;
use App\Domains\Sources\Contracts\SourceHealth;
use App\Domains\Sources\Enums\SourceHealthState;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Services\SourceRegistry;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function waveTwoSourceFixture(string $source, string $file): string
{
    return (string) file_get_contents(base_path("tests/Fixtures/Sources/{$source}/{$file}"));
}

it('runs the YouTube adapter through API preflight, search pagination, comments pagination, and extraction', function () {
    config([
        'sources.youtube.api_key' => 'test-key',
        'sources.youtube.max_comment_pages' => 2,
    ]);

    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        if ($path === '/youtube/v3/videos') {
            return Http::response('{"items":[{"id":"dQw4w9WgXcQ"}]}', 200);
        }

        if ($path === '/youtube/v3/search') {
            return Http::response(waveTwoSourceFixture(
                'youtube',
                isset($query['pageToken']) ? 'search-page-2.json' : 'search-page-1.json'
            ), 200);
        }

        if ($path === '/youtube/v3/commentThreads') {
            return Http::response(waveTwoSourceFixture(
                'youtube',
                isset($query['pageToken']) ? 'comments-page-2.json' : 'comments-page-1.json'
            ), 200);
        }

        throw new RuntimeException("Unexpected YouTube request [{$request->url()}].");
    });

    $adapter = new YouTubeAdapter;
    $firstBatch = $adapter->discover(new CrawlCursor('youtube', metadata: ['queries' => ['VPS Biznet Gio']]));
    $secondBatch = $adapter->discover($firstBatch->nextCursor);
    $fetched = $adapter->fetch($firstBatch->documents[0]);
    $opinions = iterator_to_array($adapter->extract($fetched));

    expect($adapter->preflight()->isHealthy())->toBeTrue();
    expect($firstBatch->documents)->toHaveCount(1)
        ->and($firstBatch->hasMore)->toBeTrue()
        ->and($secondBatch->documents)->toHaveCount(1)
        ->and($secondBatch->hasMore)->toBeFalse();
    expect($opinions)->toHaveCount(2)
        ->and($opinions[0]->text)->toContain('informatif')
        ->and($opinions[1]->text)->toContain('lambat');
});

it('reports YouTube as policy_disabled when its API key is missing', function () {
    config(['sources.youtube.api_key' => null]);

    Http::preventStrayRequests();

    $health = (new YouTubeAdapter)->preflight();

    expect($health)->toBeInstanceOf(SourceHealth::class)
        ->and($health->status)->toBe(SourceHealthState::PolicyDisabled)
        ->and($health->message)->toContain('YOUTUBE_API_KEY')
        ->and($health->details['reason'])->toBe('missing_api_key');
});

it('runs the KASKUS adapter only when the public page and robots preflight pass', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match (true) {
            $request->url() === 'https://www.kaskus.co.id/' => Http::response('ok'),
            $request->url() === 'https://www.kaskus.co.id/robots.txt' => Http::response("User-agent: *\nAllow: /\n"),
            str_contains($request->url(), '/search') => Http::response(waveTwoSourceFixture('kaskus', 'listing.html')),
            str_contains($request->url(), '/thread/12345/') => Http::response(waveTwoSourceFixture('kaskus', 'thread.html')),
            default => throw new RuntimeException("Unexpected KASKUS request [{$request->url()}]."),
        };
    });

    $adapter = new KaskusAdapter;
    $health = $adapter->preflight();
    $batch = $adapter->discover(new CrawlCursor('kaskus', metadata: ['query' => 'VPS']));
    $opinions = iterator_to_array($adapter->extract($adapter->fetch($batch->documents[0])));

    expect($health->isHealthy())->toBeTrue()
        ->and($batch->documents)->toHaveCount(2)
        ->and($batch->documents[0]->externalId)->toBe('12345')
        ->and($opinions)->toHaveCount(1)
        ->and($opinions[0]->text)->toContain('stabil')
        ->and($opinions[0]->text)->not->toContain('Pendapat sebelumnya')
        ->and($opinions[0]->text)->not->toContain('Promo');
});

it('rejects a KASKUS thread_url pointing outside the kaskus.co.id host without crawling it', function () {
    Http::preventStrayRequests();

    $batch = (new KaskusAdapter)->discover(new CrawlCursor('kaskus', metadata: [
        'thread_urls' => ['https://evil.example.com/thread/12345/'],
    ]));

    expect($batch->documents)->toBe([])
        ->and($batch->hasMore)->toBeFalse();
});

it('reports KASKUS as policy_disabled when robots disallows all user agents', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://www.kaskus.co.id/' => Http::response('ok'),
        'https://www.kaskus.co.id/robots.txt' => Http::response("User-agent: *\nDisallow: /\n"),
    ]);

    $health = (new KaskusAdapter)->preflight();

    expect($health->status)->toBe(SourceHealthState::PolicyDisabled)
        ->and($health->message)->toContain('robots.txt');
});

it('runs the LowEndTalk adapter only across Reviews, Providers, and Outages', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match (true) {
            $request->url() === 'https://lowendtalk.com/' => Http::response('ok'),
            str_contains($request->url(), '/categories/reviews') => Http::response(waveTwoSourceFixture('lowendtalk', 'listing.html')),
            str_contains($request->url(), '/discussion/555/') => Http::response(waveTwoSourceFixture('lowendtalk', 'thread.html')),
            default => throw new RuntimeException("Unexpected LowEndTalk request [{$request->url()}]."),
        };
    });

    $adapter = new LowEndTalkAdapter;
    $batch = $adapter->discover(new CrawlCursor('lowendtalk', metadata: [
        'category_urls' => ['https://lowendtalk.com/categories/reviews'],
    ]));
    $opinions = iterator_to_array($adapter->extract($adapter->fetch($batch->documents[0])));

    expect($adapter->preflight()->isHealthy())->toBeTrue()
        ->and($batch->documents)->toHaveCount(2)
        ->and($batch->documents[0]->externalId)->toBe('555')
        ->and($opinions)->toHaveCount(1)
        ->and($opinions[0]->text)->toContain('uptime')
        ->and($opinions[0]->text)->not->toContain('Quoted message')
        ->and($opinions[0]->text)->not->toContain('Offer');
});

it('keeps discovering LowEndTalk pages across cycles instead of freezing after the first', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match (true) {
            str_contains($request->url(), '/categories/reviews') => Http::response(waveTwoSourceFixture('lowendtalk', 'listing.html')),
            default => throw new RuntimeException("Unexpected LowEndTalk request [{$request->url()}]."),
        };
    });

    // Cold-start cursor, same as a source's very first discovery cycle: no
    // 'category_urls' in metadata yet, so it resolves from config() only —
    // this is the shape that exposed the bug in production.
    $adapter = new LowEndTalkAdapter;
    $firstBatch = $adapter->discover(new CrawlCursor('lowendtalk'));
    $secondBatch = $adapter->discover($firstBatch->nextCursor);

    expect($firstBatch->nextCursor)->not->toBeNull()
        ->and($firstBatch->nextCursor->cursorValue)->toBe('page_2')
        ->and($secondBatch->documents)->toHaveCount(2)
        ->and($secondBatch->nextCursor)->not->toBeNull()
        ->and($secondBatch->nextCursor->cursorValue)->toBe('page_3');
});

it('rejects the LowEndTalk Offers category without making a request', function () {
    Http::preventStrayRequests();

    $batch = (new LowEndTalkAdapter)->discover(new CrawlCursor('lowendtalk', metadata: [
        'category_urls' => ['https://lowendtalk.com/categories/offers'],
    ]));

    expect($batch->documents)->toBe([])
        ->and($batch->hasMore)->toBeFalse();
});

it('rejects a LowEndTalk thread_url pointing outside the lowendtalk.com host without crawling it', function () {
    Http::preventStrayRequests();

    $batch = (new LowEndTalkAdapter)->discover(new CrawlCursor('lowendtalk', metadata: [
        'thread_urls' => ['https://evil.example.com/discussion/555/'],
    ]));

    expect($batch->documents)->toBe([])
        ->and($batch->hasMore)->toBeFalse();
});

it('resolves every wave two adapter through the source registry', function () {
    $registry = app(SourceRegistry::class);

    foreach ([
        'youtube' => YouTubeAdapter::class,
        'kaskus' => KaskusAdapter::class,
        'lowendtalk' => LowEndTalkAdapter::class,
    ] as $key => $expectedClass) {
        $source = new Source(['key' => $key, 'adapter' => $key]);

        expect($registry->resolve($source))->toBeInstanceOf($expectedClass)
            ->and($registry->resolve($source))->toBeInstanceOf(SourceAdapter::class);
    }
});
