<?php

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
