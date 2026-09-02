<?php

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Search\Models\SearchQuery;

test('GET /search renders Search/Index page', function () {
    $category = Category::factory()->create(['name' => 'Cloud', 'slug' => 'cloud']);

    $this->get('/search')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Search/Index')
            ->has('categories')
            ->where('query', '')
            ->where('selectedCategory', null)
        );
});

test('GET /search?q=... passes results to Inertia page and logs query', function () {
    $category = Category::factory()->create(['name' => 'Cloud & Hosting', 'slug' => 'cloud-hosting']);
    $entity = Entity::factory()->create([
        'name' => 'Dewaweb Cloud',
        'category_id' => $category->id,
    ]);

    $this->get('/search?q=dewaweb')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Search/Index')
            ->where('query', 'dewaweb')
            ->has('results', 1)
            ->where('results.0.id', $entity->id)
            ->has('meta')
            ->has('categories')
        );

    $logged = SearchQuery::where('query', 'dewaweb')->first();
    expect($logged)->not->toBeNull()
        ->and($logged->result_count)->toBe(1);
});

test('GET /search?category=... scopes to category', function () {
    $cat1 = Category::factory()->create(['name' => 'Smartphone', 'slug' => 'smartphone']);
    $cat2 = Category::factory()->create(['name' => 'Automotive', 'slug' => 'automotive']);

    $phone = Entity::factory()->create(['name' => 'Xiaomi 14', 'category_id' => $cat1->id]);
    $car = Entity::factory()->create(['name' => 'Xiaomi SU7', 'category_id' => $cat2->id]);

    $this->get('/search?q=xiaomi&category=smartphone')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Search/Index')
            ->where('query', 'xiaomi')
            ->where('selectedCategory', 'smartphone')
            ->has('results', 1)
            ->where('results.0.id', $phone->id)
        );
});

test('GET /search with no query browses all searchable entities instead of showing none', function () {
    $category = Category::factory()->create();
    Entity::factory()->count(3)->sequence(
        ['name' => 'Alpha Brand'],
        ['name' => 'Beta Brand'],
        ['name' => 'Gamma Brand'],
    )->create(['category_id' => $category->id]);

    Entity::factory()->disabled()->create(['name' => 'Disabled Brand', 'category_id' => $category->id]);
    Entity::factory()->create(['name' => 'Unsearchable Brand', 'category_id' => $category->id, 'searchable' => false]);

    $this->get('/search')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Search/Index')
            ->where('query', '')
            ->has('results', 3)
            ->where('results.0.name', 'Alpha Brand')
        );
});

test('GET /search?category=... with no query browses only that category', function () {
    $cat1 = Category::factory()->create(['name' => 'Smartphone', 'slug' => 'smartphone']);
    $cat2 = Category::factory()->create(['name' => 'Automotive', 'slug' => 'automotive']);

    $phone = Entity::factory()->create(['name' => 'Xiaomi 14', 'category_id' => $cat1->id]);
    Entity::factory()->create(['name' => 'Xiaomi SU7', 'category_id' => $cat2->id]);

    $this->get('/search?category=smartphone')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Search/Index')
            ->where('selectedCategory', 'smartphone')
            ->has('results', 1)
            ->where('results.0.id', $phone->id)
        );
});

test('empty-query browse is not logged to search_queries', function () {
    Category::factory()->create();

    $this->get('/search')->assertOk();

    expect(SearchQuery::count())->toBe(0);
});
