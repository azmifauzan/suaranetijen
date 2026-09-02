<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Services\SeedEntityImporter;

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
