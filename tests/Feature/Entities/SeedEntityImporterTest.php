<?php

use App\Domains\Entities\Enums\CategoryStatus;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Entities\Services\SeedEntityImporter;

test('seed entity importer imports cleanly and is fully idempotent when run twice', function () {
    $importer = app(SeedEntityImporter::class);
    $csvPath = database_path('data/seed_entities.csv');

    expect(file_exists($csvPath))->toBeTrue();

    // First run
    $result1 = $importer->import($csvPath);

    expect($result1['entities'])->toBeGreaterThanOrEqual(200)
        ->and($result1['categories'])->toBeGreaterThanOrEqual(9)
        ->and($result1['aliases'])->toBeGreaterThanOrEqual(500);

    $entitiesCountAfterFirstRun = Entity::count();
    $categoriesCountAfterFirstRun = Category::count();
    $aliasesCountAfterFirstRun = EntityAlias::count();

    // Second run (DoD idempotency check)
    $result2 = $importer->import($csvPath);

    expect(Entity::count())->toBe($entitiesCountAfterFirstRun)
        ->and(Category::count())->toBe($categoriesCountAfterFirstRun)
        ->and(EntityAlias::count())->toBe($aliasesCountAfterFirstRun)
        ->and($result2['entities'])->toBe($result1['entities'])
        ->and($result2['categories'])->toBe($result1['categories'])
        ->and($result2['aliases'])->toBe($result1['aliases']);
});

test('seed entity importer configures parent-child relationships and aliases correctly', function () {
    $importer = app(SeedEntityImporter::class);
    $csvPath = database_path('data/seed_entities.csv');
    $importer->import($csvPath);

    // Verify parent brand and child product relationship
    $samsung = Entity::where('slug', 'samsung')->first();
    expect($samsung)->not->toBeNull()
        ->and($samsung->type->value)->toBe('brand')
        ->and($samsung->status)->toBe(EntityStatus::Active);

    $s24Ultra = Entity::where('slug', 'samsung-galaxy-s24-ultra')->first();
    expect($s24Ultra)->not->toBeNull()
        ->and($s24Ultra->parent_id)->toBe($samsung->id);

    // Verify category hierarchy
    $smartphoneCategory = Category::where('slug', 'smartphone')->first();
    expect($smartphoneCategory)->not->toBeNull()
        ->and($smartphoneCategory->parent)->not->toBeNull()
        ->and($smartphoneCategory->parent->slug)->toBe('technology')
        ->and($smartphoneCategory->status)->toBe(CategoryStatus::Active);

    // Verify primary alias
    $primaryAlias = EntityAlias::where('entity_id', $s24Ultra->id)
        ->where('alias', 'Samsung Galaxy S24 Ultra')
        ->first();
    expect($primaryAlias)->not->toBeNull()
        ->and($primaryAlias->normalized_alias)->toBe('samsung galaxy s24 ultra');

    // Verify custom alias
    $extraAlias = EntityAlias::where('entity_id', $s24Ultra->id)
        ->where('normalized_alias', 'galaxy s24 ultra')
        ->first();
    expect($extraAlias)->not->toBeNull();
});
