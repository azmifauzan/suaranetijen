<?php

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Search\Models\SearchQuery;
use App\Models\User;

test('executing search logs query and result count to search_queries', function () {
    $category = Category::factory()->create();
    Entity::factory()->create([
        'name' => 'Tokopedia Marketplace',
        'category_id' => $category->id,
    ]);

    expect(SearchQuery::count())->toBe(0);

    $this->getJson('/api/search?q=tokopedia')
        ->assertOk();

    expect(SearchQuery::count())->toBe(1);

    $logged = SearchQuery::first();
    expect($logged->query)->toBe('tokopedia')
        ->and($logged->normalized_query)->toBe('tokopedia')
        ->and($logged->result_count)->toBe(1)
        ->and($logged->created_at)->not->toBeNull();
});

test('zero-result search is logged with result_count = 0 for growth loop', function () {
    $this->getJson('/api/search?q=entitas-tidak-ditemukan-9999')
        ->assertOk();

    $logged = SearchQuery::where('query', 'entitas-tidak-ditemukan-9999')->first();
    expect($logged)->not->toBeNull()
        ->and($logged->result_count)->toBe(0);
});

test('search logs authenticated user id when available', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/search?q=indihome')
        ->assertOk();

    $logged = SearchQuery::where('query', 'indihome')->first();
    expect($logged)->not->toBeNull()
        ->and($logged->user_id)->toBe($user->id);
});

test('empty query is not logged', function () {
    $this->getJson('/api/search?q=')
        ->assertOk();

    expect(SearchQuery::count())->toBe(0);
});
