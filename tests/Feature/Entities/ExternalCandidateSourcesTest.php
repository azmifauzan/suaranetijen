<?php

use App\Domains\Entities\CandidateSources\CarisinyalCandidateSource;
use App\Domains\Entities\CandidateSources\DailySocialCandidateSource;
use App\Domains\Entities\CandidateSources\GoogleTrendsCandidateSource;
use App\Domains\Entities\CandidateSources\WikidataCandidateSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('parses new smartphone/vehicle model names from the Wikidata SPARQL endpoint', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        expect($request->url())->toContain('query.wikidata.org/sparql');

        return Http::response([
            'results' => [
                'bindings' => [
                    ['itemLabel' => ['value' => 'Samsung Galaxy A58']],
                    ['itemLabel' => ['value' => 'Toyota Yaris Cross Hybrid']],
                ],
            ],
        ]);
    });

    $candidates = (new WikidataCandidateSource)->discover();

    expect($candidates)->toHaveCount(2)
        ->and(collect($candidates)->pluck('raw_term'))->toContain('samsung galaxy a58', 'toyota yaris cross hybrid')
        ->and((new WikidataCandidateSource)->sourceType())->toBe('wikidata');
});

it('discards Wikidata items whose label service fell back to a raw entity id', function () {
    Http::preventStrayRequests();
    Http::fake(fn () => Http::response([
        'results' => [
            'bindings' => [
                ['itemLabel' => ['value' => 'Samsung Galaxy A58']],
                // No label in any requested language — the service returns the
                // bare entity id instead, not a real name.
                ['itemLabel' => ['value' => 'Q141025060']],
            ],
        ],
    ]));

    $candidates = (new WikidataCandidateSource)->discover();

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]['raw_term'])->toBe('samsung galaxy a58');
});

it('parses new brand/startup names from the DailySocial RSS feed', function () {
    Http::preventStrayRequests();
    Http::fake(fn () => Http::response(
        '<?xml version="1.0"?><rss><channel>'
        .'<item><title>Startup Baru XYZ Raih Pendanaan Seri A</title></item>'
        .'<item><title>Platform Logistik ABC Ekspansi ke Indonesia</title></item>'
        .'</channel></rss>'
    ));

    $candidates = (new DailySocialCandidateSource)->discover();

    expect($candidates)->toHaveCount(2)
        ->and((new DailySocialCandidateSource)->sourceType())->toBe('daily_social');
});

it('splits a multi-story roundup title into one candidate per comma-separated clause', function () {
    Http::preventStrayRequests();
    Http::fake(fn () => Http::response(
        '<?xml version="1.0"?><rss><channel>'
        .'<item><title>Ajaib Lifts Off, Tiptip Turns Profitable, Danantara Enters GoTo</title></item>'
        .'</channel></rss>'
    ));

    $candidates = (new DailySocialCandidateSource)->discover();

    expect(collect($candidates)->pluck('raw_term')->all())->toBe([
        'Ajaib Lifts Off',
        'Tiptip Turns Profitable',
        'Danantara Enters GoTo',
    ]);
});

it('parses trending Indonesian search terms with approx_traffic as weight', function () {
    Http::preventStrayRequests();
    Http::fake(fn () => Http::response(
        '<?xml version="1.0"?><rss xmlns:ht="http://trends.google.com/trending/rss"><channel>'
        .'<item><title>iphone 17 pro max</title><ht:approx_traffic>5000+</ht:approx_traffic></item>'
        .'</channel></rss>'
    ));

    $candidates = (new GoogleTrendsCandidateSource)->discover();

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]['raw_term'])->toBe('iphone 17 pro max')
        ->and($candidates[0]['weight'])->toBe(5000)
        ->and((new GoogleTrendsCandidateSource)->sourceType())->toBe('google_trends');
});

it('parses device names from Carisinyal\'s hp sitemaps, newest first, excluding the listing index', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match ($request->url()) {
            'https://carisinyal.com/hp-sitemap1.xml' => Http::response(
                '<?xml version="1.0"?><urlset>'
                .'<url><loc>https://carisinyal.com/hp/</loc><lastmod>2026-08-17T03:06:13+00:00</lastmod></url>'
                .'<url><loc>https://carisinyal.com/hp/vivo-y500-2/</loc><lastmod>2026-08-16T10:06:18+00:00</lastmod></url>'
                .'<url><loc>https://carisinyal.com/hp/itel-s26-ultra/</loc><lastmod>2026-08-10T10:06:11+00:00</lastmod></url>'
                .'</urlset>'
            ),
            'https://carisinyal.com/hp-sitemap2.xml',
            'https://carisinyal.com/hp-sitemap3.xml',
            'https://carisinyal.com/hp-sitemap4.xml' => Http::response('<?xml version="1.0"?><urlset></urlset>'),
            default => throw new RuntimeException("Unexpected Carisinyal request [{$request->url()}]."),
        };
    });

    $candidates = (new CarisinyalCandidateSource)->discover();

    expect($candidates)->toHaveCount(2)
        ->and($candidates[0]['raw_term'])->toBe('Vivo Y500 2')
        ->and($candidates[1]['raw_term'])->toBe('Itel S26 Ultra')
        ->and($candidates[0]['weight'])->toBeGreaterThan($candidates[1]['weight'])
        ->and((new CarisinyalCandidateSource)->sourceType())->toBe('carisinyal');
});
