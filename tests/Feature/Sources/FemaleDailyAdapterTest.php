<?php

use App\Domains\Sources\Adapters\FemaleDailyAdapter;
use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\SourceAdapter;
use App\Domains\Sources\Contracts\SourceDocumentRef;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Services\SourceRegistry;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function femaledailyFixture(string $file): string
{
    return (string) file_get_contents(base_path("tests/Fixtures/Sources/femaledaily/{$file}"));
}

it('refreshes the brand list for the current letter and stores it without fetching products yet', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match ($request->url()) {
            'https://reviews.femaledaily.com/brands?type=product&alphabet=A&origin=&sort=name' => Http::response(femaledailyFixture('brands_list.html')),
            default => throw new RuntimeException("Unexpected FemaleDaily request [{$request->url()}]."),
        };
    });

    $adapter = new FemaleDailyAdapter;
    $batch = $adapter->discover(CrawlCursor::initial('femaledaily'));

    expect($batch->documents)->toBe([])
        ->and($batch->hasMore)->toBeTrue()
        ->and($batch->nextCursor->metadata['brands'])->toBe(['contoh-brand', 'merek-lain'])
        ->and($batch->nextCursor->metadata['brand_index'])->toBe(0)
        ->and($batch->nextCursor->metadata['letter_index'])->toBe(0);
});

it('skips an empty alphabet bucket straight to the next letter', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match ($request->url()) {
            'https://reviews.femaledaily.com/brands?type=product&alphabet=B&origin=&sort=name' => Http::response(femaledailyFixture('brands_empty.html')),
            default => throw new RuntimeException("Unexpected FemaleDaily request [{$request->url()}]."),
        };
    });

    $adapter = new FemaleDailyAdapter;
    $cursor = new CrawlCursor('femaledaily', metadata: ['letter_index' => 1, 'brands' => []]);
    $batch = $adapter->discover($cursor);

    expect($batch->documents)->toBe([])
        ->and($batch->hasMore)->toBeFalse()
        ->and($batch->nextCursor->metadata['letter_index'])->toBe(2);
});

it('fetches a brand product page, skips zero-review products, and constructs the canonical URL from category ancestry', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match ($request->url()) {
            'https://reviews.femaledaily.com/brands/product/contoh-brand?page=1' => Http::response(femaledailyFixture('brand_products_page1.html')),
            default => throw new RuntimeException("Unexpected FemaleDaily request [{$request->url()}]."),
        };
    });

    $adapter = new FemaleDailyAdapter;
    $cursor = new CrawlCursor('femaledaily', metadata: [
        'letter_index' => 0,
        'brands' => ['contoh-brand', 'merek-lain'],
        'brand_index' => 0,
        'product_page' => 1,
    ]);
    $batch = $adapter->discover($cursor);

    expect($batch->documents)->toHaveCount(1)
        ->and($batch->documents[0]->externalId)->toBe('contoh-brand:contoh-serum-wajah')
        ->and($batch->documents[0]->canonicalUrl)->toBe('https://reviews.femaledaily.com/products/root-category/leaf-category/contoh-brand/contoh-serum-wajah')
        ->and($batch->nextCursor->metadata['brand_index'])->toBe(1)
        ->and($batch->nextCursor->metadata['letter_index'])->toBe(0)
        ->and($batch->nextCursor->metadata['product_page'])->toBe(1);
});

it('extracts review text only, never the reviewer username', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match ($request->url()) {
            'https://reviews.femaledaily.com/products/root-category/leaf-category/contoh-brand/contoh-serum-wajah' => Http::response(femaledailyFixture('product_review.html')),
            default => throw new RuntimeException("Unexpected FemaleDaily request [{$request->url()}]."),
        };
    });

    $adapter = new FemaleDailyAdapter;
    $ref = new SourceDocumentRef(
        sourceKey: 'femaledaily',
        externalId: 'contoh-brand:contoh-serum-wajah',
        canonicalUrl: 'https://reviews.femaledaily.com/products/root-category/leaf-category/contoh-brand/contoh-serum-wajah'
    );

    $opinions = iterator_to_array($adapter->extract($adapter->fetch($ref)));

    expect($opinions)->toHaveCount(2)
        ->and($opinions[0]->text)->toContain('gak lengket')
        ->and($opinions[1]->text)->toContain('baunya agak menyengat')
        ->and($opinions[0]->text)->not->toContain('contohreviewer1')
        ->and($opinions[1]->text)->not->toContain('contohreviewer2')
        ->and($opinions[0]->text)->not->toContain('display: flex');
});

it('resolves the FemaleDaily adapter through the source registry', function () {
    $registry = app(SourceRegistry::class);
    $source = new Source(['key' => 'femaledaily', 'adapter' => 'femaledaily']);

    expect($registry->resolve($source))->toBeInstanceOf(FemaleDailyAdapter::class)
        ->and($registry->resolve($source))->toBeInstanceOf(SourceAdapter::class);
});
