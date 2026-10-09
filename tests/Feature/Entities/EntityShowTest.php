<?php

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\SeedEntityImporter;
use App\Domains\Sentiment\Models\SentimentSnapshot;

test('public route /e/{slug} resolves 200 for active entity', function () {
    $importer = app(SeedEntityImporter::class);
    $importer->import(database_path('data/seed_entities.csv'));

    $this->get('/e/samsung')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Entities/Show')
            ->where('entity.name', 'Samsung')
            ->where('entity.slug', 'samsung')
            ->where('entity.type', 'brand')
            ->has('entity.category')
            ->has('entity.aliases')
        );
});

test('public route /e/{slug} resolves for all imported seed entities', function () {
    $importer = app(SeedEntityImporter::class);
    $importer->import(database_path('data/seed_entities.csv'));

    $sampleSlugs = [
        'samsung',
        'samsung-galaxy-s24-ultra',
        'iphone-16-pro-max',
        'toyota-avanza',
        'honda-beat',
        'biznet-gio',
        'vps-biznet-gio',
        'idcloudhost',
        'telkomsel',
        'indihome',
        'tokopedia',
        'gojek',
        'indomie',
    ];

    foreach ($sampleSlugs as $slug) {
        $this->get("/e/{$slug}")
            ->assertOk();
    }
});

test('public route /e/{slug} returns 404 for non-existent entity', function () {
    $this->get('/e/entitas-yang-tidak-ada-12345')
        ->assertNotFound();
});

test('public route /e/{slug} returns 404 for disabled entity', function () {
    $entity = Entity::factory()->disabled()->create(['slug' => 'disabled-entity']);

    $this->get("/e/{$entity->slug}")
        ->assertNotFound();
});

test('related entities link only publicly eligible entity pages', function () {
    $category = Category::factory()->create();
    $page = Entity::factory()->create(['category_id' => $category->id]);
    $eligible = Entity::factory()->create(['category_id' => $category->id, 'name' => 'Eligible Peer']);
    SentimentSnapshot::factory()->create(['entity_id' => $eligible->id]);
    $thin = Entity::factory()->create(['category_id' => $category->id, 'name' => 'Thin Peer']);
    SentimentSnapshot::factory()->create(['entity_id' => $thin->id, 'opinion_count' => 5]);
    Entity::factory()->create(['category_id' => $category->id, 'name' => 'No Data Peer']);

    $this->get('/e/'.$page->slug)
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->has('relatedEntities', 1)
            ->where('relatedEntities.0.slug', $eligible->slug)
        );
});
