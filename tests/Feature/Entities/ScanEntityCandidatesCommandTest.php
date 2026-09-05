<?php

use App\Domains\Entities\Models\EntityCandidate;
use App\Domains\Entities\Models\LlmSetting;
use App\Domains\Search\Models\SearchQuery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('runs end to end: discovers, enriches, and persists a candidate for review', function () {
    LlmSetting::create(['base_url' => 'https://llm.internal/v1', 'model' => 'test-model', 'api_key' => 'key']);
    SearchQuery::factory()->zeroResults()->count(5)->create([
        'query' => 'iPhone 17 Pro',
        'normalized_query' => 'iphone 17 pro',
    ]);

    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        return match (true) {
            str_contains($request->url(), 'query.wikidata.org') => Http::response(['results' => ['bindings' => []]]),
            str_contains($request->url(), 'dailysocial.id') => Http::response('<?xml version="1.0"?><rss><channel></channel></rss>'),
            str_contains($request->url(), 'trends.google.com') => Http::response('<?xml version="1.0"?><rss><channel></channel></rss>'),
            str_contains($request->url(), 'llm.internal') => Http::response(['choices' => [['message' => ['content' => json_encode([
                'suggested_name' => 'iPhone 17 Pro',
                'suggested_entity_type' => 'product',
                'suggested_category' => 'Smartphone',
                'suggested_aliases' => ['iphone 17 pro'],
                'reasoning' => 'r',
            ])]]]]),
            default => throw new RuntimeException("Unexpected request [{$request->url()}]."),
        };
    });

    $this->artisan('entities:scan-candidates')->assertSuccessful();

    expect(EntityCandidate::query()->where('normalized_term', 'iphone 17 pro')->exists())->toBeTrue();
});
