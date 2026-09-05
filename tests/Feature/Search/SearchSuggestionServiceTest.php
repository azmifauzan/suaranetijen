<?php

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Search\Models\SearchQuery;
use App\Domains\Sentiment\Models\SentimentSnapshot;

test('homepage interleaves popular searches with highest eligible sentiment entities', function () {
    $category = Category::factory()->create();
    $highestScoreEntity = Entity::factory()->create([
        'name' => 'Samsung Galaxy S26',
        'category_id' => $category->id,
    ]);
    $secondHighestScoreEntity = Entity::factory()->create([
        'name' => 'Dewaweb Cloud',
        'category_id' => $category->id,
    ]);

    SentimentSnapshot::factory()->create([
        'entity_id' => $highestScoreEntity->id,
        'positive_count' => 95,
        'neutral_count' => 5,
        'negative_count' => 0,
        'opinion_count' => 100,
        'score' => 97.5,
    ]);
    SentimentSnapshot::factory()->create([
        'entity_id' => $secondHighestScoreEntity->id,
        'positive_count' => 80,
        'neutral_count' => 10,
        'negative_count' => 10,
        'opinion_count' => 100,
        'score' => 85.0,
    ]);

    SearchQuery::factory()->count(3)->create([
        'query' => 'vps biznet',
        'normalized_query' => 'vps biznet',
        'result_count' => 10,
    ]);
    SearchQuery::factory()->count(2)->create([
        'query' => 'samsung',
        'normalized_query' => 'samsung',
        'result_count' => 10,
    ]);

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->has('searchSuggestions', 4)
            ->where('searchSuggestions.0.query', 'vps biznet')
            ->where('searchSuggestions.0.source', 'trending')
            ->where('searchSuggestions.1.query', 'Samsung Galaxy S26')
            ->where('searchSuggestions.1.source', 'top_score')
            ->where('searchSuggestions.2.query', 'samsung')
            ->where('searchSuggestions.2.source', 'trending')
            ->where('searchSuggestions.3.query', 'Dewaweb Cloud')
            ->where('searchSuggestions.3.source', 'top_score')
        );
});

test('homepage excludes zero-result searches and entities below the public score threshold', function () {
    $category = Category::factory()->create();
    $eligibleEntity = Entity::factory()->create([
        'name' => 'Eligible Service',
        'category_id' => $category->id,
    ]);
    $ineligibleEntity = Entity::factory()->create([
        'name' => 'Not Enough Opinions',
        'category_id' => $category->id,
    ]);

    SentimentSnapshot::factory()->create([
        'entity_id' => $eligibleEntity->id,
        'opinion_count' => 30,
        'score' => 75.0,
    ]);
    SentimentSnapshot::factory()->create([
        'entity_id' => $ineligibleEntity->id,
        'opinion_count' => 29,
        'score' => 99.0,
    ]);
    SearchQuery::factory()->create([
        'query' => 'eligible',
        'normalized_query' => 'eligible',
        'result_count' => 1,
    ]);
    SearchQuery::factory()->zeroResults()->create([
        'query' => 'private failed search',
        'normalized_query' => 'private failed search',
    ]);

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->has('searchSuggestions', 2)
            ->where('searchSuggestions.0.query', 'eligible')
            ->where('searchSuggestions.0.source', 'trending')
            ->where('searchSuggestions.1.query', 'Eligible Service')
            ->where('searchSuggestions.1.source', 'top_score')
        );
});
