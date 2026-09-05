<?php

use App\Domains\Entities\CandidateSources\SearchQueryCandidateSource;
use App\Domains\Search\Models\SearchQuery;

it('groups zero-result search queries by normalized query and ranks by frequency', function () {
    SearchQuery::factory()->zeroResults()->count(5)->create(['query' => 'iPhone 17', 'normalized_query' => 'iphone 17']);
    SearchQuery::factory()->zeroResults()->count(2)->create(['query' => 'iphone17', 'normalized_query' => 'iphone 17']);
    SearchQuery::factory()->zeroResults()->create(['query' => 'Something Rare', 'normalized_query' => 'something rare']);
    SearchQuery::factory()->create(['query' => 'Samsung', 'normalized_query' => 'samsung', 'result_count' => 10]);

    $candidates = (new SearchQueryCandidateSource)->discover();

    $iphone = collect($candidates)->firstWhere('raw_term', 'iphone 17');

    expect($candidates)->toHaveCount(1)
        ->and($iphone['weight'])->toBe(7)
        ->and(collect($candidates)->pluck('raw_term'))
        ->not->toContain('samsung')
        ->not->toContain('something rare');
});

it('excludes normalized queries below the minimum frequency threshold', function () {
    SearchQuery::factory()->zeroResults()->count(2)->create(['query' => 'rare thing', 'normalized_query' => 'rare thing']);

    $candidates = (new SearchQueryCandidateSource)->discover();

    expect($candidates)->toBe([]);
});
