<?php

use App\Domains\Sources\Adapters\BlueskyAdapter;
use App\Domains\Sources\Adapters\DiskusiWebHostingAdapter;
use App\Domains\Sources\Adapters\IndoForumAdapter;
use App\Domains\Sources\Adapters\SerayaMotorAdapter;
use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\DiscoveryBatch;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceAdapter;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Services\SourceRegistry;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function sourceFixture(string $source, string $file): string
{
    return (string) file_get_contents(base_path("tests/Fixtures/Sources/{$source}/{$file}"));
}

it('runs the DiskusiWebHosting adapter through preflight, discovery, fetch, and extraction', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match (true) {
            $request->url() === 'https://www.diskusiwebhosting.com/' => Http::response('ok'),
            str_contains($request->url(), 'index.php') => Http::response(sourceFixture('diskusiwebhosting', 'feed.xml')),
            str_contains($request->url(), '/threads/') => Http::response(sourceFixture('diskusiwebhosting', 'thread.html')),
            default => Http::response('', 404),
        };
    });

    $adapter = new DiskusiWebHostingAdapter;
    $batch = $adapter->discover(CrawlCursor::initial('diskusiwebhosting'));
    $opinions = iterator_to_array($adapter->extract($adapter->fetch($batch->documents[0])));

    expect($adapter->preflight()->isHealthy())->toBeTrue()
        ->and($batch)->toBeInstanceOf(DiscoveryBatch::class)
        ->and($batch->documents)->toHaveCount(1)
        ->and($opinions)->toHaveCount(1)
        ->and($opinions[0]->text)->toContain('stabil')
        ->and($opinions[0]->text)->not->toContain('promo');
});

it('runs the SerayaMotor adapter and removes quoted and promotional text', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match (true) {
            $request->url() === 'https://www.serayamotor.com/diskusi/' => Http::response('ok'),
            str_contains($request->url(), 'viewforum.php') => Http::response(sourceFixture('serayamotor', 'listing.html')),
            str_contains($request->url(), 'viewtopic.php') => Http::response(sourceFixture('serayamotor', 'thread.html')),
            default => Http::response('', 404),
        };
    });

    $adapter = new SerayaMotorAdapter;
    $batch = $adapter->discover(CrawlCursor::initial('serayamotor'));
    $opinions = iterator_to_array($adapter->extract($adapter->fetch($batch->documents[0])));

    expect($adapter->preflight()->isHealthy())->toBeTrue()
        ->and($batch->documents)->toHaveCount(2)
        ->and($opinions)->toHaveCount(1)
        ->and($opinions[0]->text)->toContain('nyaman')
        ->and($opinions[0]->text)->not->toContain('Mobil lama')
        ->and($opinions[0]->text)->not->toContain('Jual mobil');
});

it('rotates to the next SerayaMotor forum once the current one has no threads', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match (true) {
            $request->url() === 'https://www.serayamotor.com/diskusi/' => Http::response('ok'),
            str_contains($request->url(), 'f=19') => Http::response('<html><body>no threads here</body></html>'),
            str_contains($request->url(), 'f=64') => Http::response(sourceFixture('serayamotor', 'listing.html')),
            default => Http::response('', 404),
        };
    });

    $adapter = new SerayaMotorAdapter;
    $firstBatch = $adapter->discover(CrawlCursor::initial('serayamotor'));
    $secondBatch = $adapter->discover($firstBatch->nextCursor);

    expect($firstBatch->documents)->toBe([])
        ->and($firstBatch->nextCursor->metadata['forum_index'])->toBe(1)
        ->and($firstBatch->nextCursor->metadata['page'])->toBe(1)
        ->and($secondBatch->documents)->toHaveCount(2);
});

it('wraps an exhausted SerayaMotor forum back to page 1 instead of paginating forever', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match (true) {
            $request->url() === 'https://www.serayamotor.com/diskusi/' => Http::response('ok'),
            str_contains($request->url(), 'f=63') => Http::response('<html><body>no threads here</body></html>'),
            default => Http::response('', 404),
        };
    });

    $adapter = new SerayaMotorAdapter;
    $batch = $adapter->discover(new CrawlCursor('serayamotor', metadata: ['forum_index' => 2, 'page' => 5]));

    expect($batch->documents)->toBe([])
        ->and($batch->nextCursor->metadata['forum_index'])->toBe(0)
        ->and($batch->nextCursor->metadata['page'])->toBe(1);
});

it('runs the allowlisted IndoForum adapter without discovering other forum ids', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match (true) {
            $request->url() === 'https://www.forum.or.id/' => Http::response('ok'),
            str_contains($request->url(), '/forums/') => Http::response(sourceFixture('indoforum', 'listing.html')),
            str_contains($request->url(), '/threads/') => Http::response(sourceFixture('indoforum', 'thread.html')),
            default => Http::response('', 404),
        };
    });

    $adapter = new IndoForumAdapter;
    $batch = $adapter->discover(new CrawlCursor('indoforum', metadata: ['forum_ids' => [139]]));
    $opinions = iterator_to_array($adapter->extract($adapter->fetch($batch->documents[0])));

    expect($adapter->preflight()->isHealthy())->toBeTrue()
        ->and($batch->documents)->toHaveCount(1)
        ->and($batch->documents[0]->externalId)->toBe('789')
        ->and($opinions)->toHaveCount(1);
});

it('rejects an IndoForum forum id outside the allowlist without crawling it', function () {
    Http::preventStrayRequests();

    $adapter = new IndoForumAdapter;
    $batch = $adapter->discover(new CrawlCursor('indoforum', metadata: ['forum_ids' => [999999]]));

    expect($batch->documents)->toBe([])
        ->and($batch->hasMore)->toBeFalse();
});

it('rotates to the next allowed IndoForum forum once the current one has no threads', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match (true) {
            $request->url() === 'https://www.forum.or.id/' => Http::response('ok'),
            str_contains($request->url(), 'forum-komplain.139') => Http::response('<html><body>no threads here</body></html>'),
            str_contains($request->url(), 'info-terbaru-reviews.107') => Http::response(sourceFixture('indoforum', 'listing.html')),
            default => Http::response('', 404),
        };
    });

    $adapter = new IndoForumAdapter;
    $firstBatch = $adapter->discover(new CrawlCursor('indoforum', metadata: ['forum_ids' => [139, 107]]));
    $secondBatch = $adapter->discover($firstBatch->nextCursor);

    expect($firstBatch->documents)->toBe([])
        ->and($firstBatch->nextCursor->metadata['forum_index'])->toBe(1)
        ->and($firstBatch->nextCursor->metadata['page'])->toBe(1)
        ->and($secondBatch->documents)->toHaveCount(1)
        ->and($secondBatch->documents[0]->externalId)->toBe('789');
});

it('wraps an exhausted IndoForum forum back to page 1 instead of paginating forever', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match (true) {
            $request->url() === 'https://www.forum.or.id/' => Http::response('ok'),
            str_contains($request->url(), 'forum-komplain.139') => Http::response('<html><body>no threads here</body></html>'),
            default => Http::response('', 404),
        };
    });

    $adapter = new IndoForumAdapter;
    $batch = $adapter->discover(new CrawlCursor('indoforum', metadata: ['forum_ids' => [139], 'page' => 32]));

    expect($batch->documents)->toBe([])
        ->and($batch->nextCursor->metadata['forum_index'])->toBe(0)
        ->and($batch->nextCursor->metadata['page'])->toBe(1);
});

it('runs the Bluesky Jetstream adapter and filters posts by normalized alias', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match (true) {
            $request->url() === 'https://jetstream2.us-east.bsky.network/' => Http::response('ok'),
            str_contains($request->url(), '/subscribe') => Http::response(sourceFixture('bluesky', 'events.ndjson')),
            default => Http::response('', 404),
        };
    });

    $adapter = new BlueskyAdapter;
    $batch = $adapter->discover(new CrawlCursor('bluesky', metadata: ['aliases' => ['VPS Biznet Gio']]));
    $fetched = $adapter->fetch($batch->documents[0]);
    $opinions = iterator_to_array($adapter->extract($fetched));

    expect($adapter->preflight()->isHealthy())->toBeTrue()
        ->and($batch->documents)->toHaveCount(1)
        ->and($fetched)->toBeInstanceOf(FetchedDocument::class)
        ->and($opinions)->toHaveCount(1)
        ->and($opinions[0]->text)->toContain('stabil');
});

it('resolves all wave one adapters through the source registry', function () {
    $registry = app(SourceRegistry::class);

    foreach ([
        'diskusiwebhosting' => DiskusiWebHostingAdapter::class,
        'serayamotor' => SerayaMotorAdapter::class,
        'indoforum' => IndoForumAdapter::class,
        'bluesky' => BlueskyAdapter::class,
    ] as $key => $expectedClass) {
        $source = new Source(['key' => $key, 'adapter' => $key]);

        expect($registry->resolve($source))->toBeInstanceOf($expectedClass)
            ->and($registry->resolve($source))->toBeInstanceOf(SourceAdapter::class);
    }
});
