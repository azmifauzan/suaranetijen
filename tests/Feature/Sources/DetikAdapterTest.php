<?php

use App\Domains\Sources\Adapters\DetikAdapter;
use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\SourceAdapter;
use App\Domains\Sources\Contracts\SourceDocumentRef;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Services\SourceRegistry;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function detikFixture(string $file): string
{
    return (string) file_get_contents(base_path("tests/Fixtures/Sources/detik/{$file}"));
}

it('runs the Detik adapter through discovery, sitemap parsing, and desk rotation', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match ($request->url()) {
            'https://oto.detik.com/motor/sitemap_news.xml' => Http::response(detikFixture('sitemap_news.xml')),
            default => throw new RuntimeException("Unexpected Detik request [{$request->url()}]."),
        };
    });

    $adapter = new DetikAdapter;
    $batch = $adapter->discover(CrawlCursor::initial('detik'));

    expect($batch->documents)->toHaveCount(2)
        ->and($batch->documents[0]->externalId)->toBe('8649432')
        ->and($batch->documents[0]->canonicalUrl)->toBe('https://oto.detik.com/motor/d-8649432/royal-enfield-himalayan-440-meluncur-harga-rp-40-jutaan')
        ->and($batch->documents[0]->title)->toBe('Royal Enfield Himalayan 440 Meluncur, Harga Rp 40 Jutaan')
        ->and($batch->documents[1]->externalId)->toBe('8648481')
        ->and($batch->nextCursor->metadata['desk_index'])->toBe(1);
});

it('wraps the desk index back to zero after the last configured desk', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match ($request->url()) {
            'https://hot.detik.com/celebs/sitemap_news.xml' => Http::response(detikFixture('sitemap_news.xml')),
            default => throw new RuntimeException("Unexpected Detik request [{$request->url()}]."),
        };
    });

    $adapter = new DetikAdapter;
    $cursor = new CrawlCursor('detik', metadata: [
        'desks' => ['https://oto.detik.com/motor/sitemap_news.xml', 'https://hot.detik.com/celebs/sitemap_news.xml'],
        'desk_index' => 1,
    ]);
    $batch = $adapter->discover($cursor);

    expect($batch->nextCursor->metadata['desk_index'])->toBe(0);
});

it('fetches and paginates comments via the GraphQL API, using the inline-JS kanal template', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        if ($request->url() === 'https://oto.detik.com/motor/d-8649432/royal-enfield-himalayan-440-meluncur-harga-rp-40-jutaan') {
            return Http::response(detikFixture('article_inline.html'));
        }

        if ($request->url() === 'https://apicomment.detik.com/graphql') {
            $variables = $request->data()['variables'];
            expect($variables['query'][0])->toBe(['name' => 'news.artikel', 'terms' => 8649432]);

            return $variables['page'] === 1
                ? Http::response(detikFixture('graphql_page1.json'))
                : Http::response(detikFixture('graphql_page2.json'));
        }

        throw new RuntimeException("Unexpected Detik request [{$request->url()}].");
    });

    $adapter = new DetikAdapter;
    $ref = new SourceDocumentRef(
        sourceKey: 'detik',
        externalId: '8649432',
        canonicalUrl: 'https://oto.detik.com/motor/d-8649432/royal-enfield-himalayan-440-meluncur-harga-rp-40-jutaan'
    );

    $fetched = $adapter->fetch($ref);
    $payload = json_decode($fetched->rawPayload, true);

    // page1 has 2 top-level results (one with a child reply) + page2 has 2
    // more (one null child, one empty-content skipped later in extract()).
    expect($payload['results'])->toHaveCount(4);

    $opinions = iterator_to_array($adapter->extract($fetched));

    expect($opinions)->toHaveCount(4) // 2 top-level + 1 child on page1, + 1 non-empty on page2
        ->and(collect($opinions)->pluck('externalItemId')->all())->toBe(['76501710', '76501800', '76501711', '76501712'])
        ->and($opinions[0]->text)->toContain('estimasi harganya');

    foreach ($opinions as $opinion) {
        expect($opinion->metadata)->toBe(['adapter' => 'detik'])
            ->and($opinion->text)->not->toContain('Gigibolong')
            ->and($opinion->text)->not->toContain('MotorFans99');
    }
});

it('parses kanal from the JSON-embedded comment config template used on wolipop', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        if ($request->url() === 'https://wolipop.detik.com/foto-fashion/d-8649983/foto-naomi-osaka-stylish-di-us-open-2026-deja-vu-gaya-zendaya-di-challengers') {
            return Http::response(detikFixture('article_json.html'));
        }

        if ($request->url() === 'https://apicomment.detik.com/graphql') {
            $variables = $request->data()['variables'];
            expect($variables['query'][0])->toBe(['name' => 'news.artikel', 'terms' => 8649983]);

            return Http::response(detikFixture('graphql_empty.json'));
        }

        throw new RuntimeException("Unexpected Detik request [{$request->url()}].");
    });

    $adapter = new DetikAdapter;
    $ref = new SourceDocumentRef(
        sourceKey: 'detik',
        externalId: '8649983',
        canonicalUrl: 'https://wolipop.detik.com/foto-fashion/d-8649983/foto-naomi-osaka-stylish-di-us-open-2026-deja-vu-gaya-zendaya-di-challengers'
    );

    $fetched = $adapter->fetch($ref);
    $payload = json_decode($fetched->rawPayload, true);

    expect($payload['results'])->toBe([]);
});

it('returns no opinions for an article with no comment widget at all', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match ($request->url()) {
            'https://oto.detik.com/motor/d-8648481/alasan-vinfast-viper-cocok-jadi-motor-listrik-urban-yang-stylish-gesit' => Http::response(detikFixture('article_no_comment.html')),
            default => throw new RuntimeException("Unexpected Detik request [{$request->url()}]."),
        };
    });

    $adapter = new DetikAdapter;
    $ref = new SourceDocumentRef(
        sourceKey: 'detik',
        externalId: '8648481',
        canonicalUrl: 'https://oto.detik.com/motor/d-8648481/alasan-vinfast-viper-cocok-jadi-motor-listrik-urban-yang-stylish-gesit'
    );

    $opinions = iterator_to_array($adapter->extract($adapter->fetch($ref)));

    expect($opinions)->toBe([]);
});

it('never requests author, liker, or dislike fields in the comment GraphQL query', function () {
    $adapter = new DetikAdapter;
    $reflection = new ReflectionClass($adapter);
    $query = $reflection->getConstant('COMMENT_QUERY');

    expect($query)->not->toContain('author')
        ->and($query)->not->toContain('liker')
        ->and($query)->not->toContain('dislike')
        ->and($query)->not->toContain('reporter');
});

it('resolves the Detik adapter through the source registry', function () {
    $registry = app(SourceRegistry::class);
    $source = new Source(['key' => 'detik', 'adapter' => 'detik']);

    expect($registry->resolve($source))->toBeInstanceOf(DetikAdapter::class)
        ->and($registry->resolve($source))->toBeInstanceOf(SourceAdapter::class);
});
