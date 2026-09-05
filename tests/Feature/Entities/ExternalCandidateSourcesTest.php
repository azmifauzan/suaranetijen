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
