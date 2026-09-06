<?php

use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\CarSpec;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\MotorcycleSpec;
use App\Domains\Entities\Models\SmartphoneSpec;
use App\Models\User;

function entityDetailSpecUpdatePayload(Entity $entity, array $overrides = []): array
{
    return array_merge([
        'name' => $entity->name,
        'slug' => $entity->slug,
        'type' => $entity->type->value,
        'category_id' => $entity->category_id,
        'description' => $entity->description,
        'status' => $entity->status->value,
        'searchable' => $entity->searchable,
        'rankable' => $entity->rankable,
    ], $overrides);
}

test('admin can save smartphone spec for a smartphone-category entity', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create(['slug' => 'smartphone']);
    $entity = Entity::factory()->create(['category_id' => $category->id, 'type' => EntityType::Product]);

    $this->actingAs($admin)
        ->put("/admin/entities/{$entity->id}", entityDetailSpecUpdatePayload($entity, [
            'smartphone_spec' => [
                'chipset' => 'Snapdragon 7 Gen 4',
                'ram' => '8/12 GB',
                'battery_mah' => 5000,
                'release_year' => 2026,
            ],
        ]))
        ->assertRedirect();

    $this->assertDatabaseHas('smartphone_specs', [
        'entity_id' => $entity->id,
        'chipset' => 'Snapdragon 7 Gen 4',
        'ram' => '8/12 GB',
        'battery_mah' => 5000,
        'release_year' => 2026,
    ]);
});

test('spec block for a non-matching category is not persisted', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create(['slug' => 'smartphone']);
    $entity = Entity::factory()->create(['category_id' => $category->id, 'type' => EntityType::Product]);

    $this->actingAs($admin)
        ->put("/admin/entities/{$entity->id}", entityDetailSpecUpdatePayload($entity, [
            'car_spec' => ['body_type' => 'SUV'],
        ]))
        ->assertRedirect();

    expect(CarSpec::where('entity_id', $entity->id)->exists())->toBeFalse();
});

test('admin can save person profile for a person-type entity regardless of category slug', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create(['slug' => 'politisi']);
    $entity = Entity::factory()->create(['category_id' => $category->id, 'type' => EntityType::Person]);

    $this->actingAs($admin)
        ->put("/admin/entities/{$entity->id}", entityDetailSpecUpdatePayload($entity, [
            'person_profile' => [
                'occupation' => 'Gubernur',
                'affiliation' => 'Partai Contoh',
                'active_since_year' => 2010,
            ],
        ]))
        ->assertRedirect();

    $this->assertDatabaseHas('person_profiles', [
        'entity_id' => $entity->id,
        'occupation' => 'Gubernur',
        'affiliation' => 'Partai Contoh',
        'active_since_year' => 2010,
    ]);
});

test('resubmitting a spec updates the existing row instead of creating a duplicate', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create(['slug' => 'motor']);
    $entity = Entity::factory()->create(['category_id' => $category->id, 'type' => EntityType::Product]);

    $this->actingAs($admin)->put("/admin/entities/{$entity->id}", entityDetailSpecUpdatePayload($entity, [
        'motorcycle_spec' => ['engine_cc' => 150],
    ]));
    $this->actingAs($admin)->put("/admin/entities/{$entity->id}", entityDetailSpecUpdatePayload($entity, [
        'motorcycle_spec' => ['engine_cc' => 160],
    ]));

    expect(MotorcycleSpec::where('entity_id', $entity->id)->count())->toBe(1)
        ->and(MotorcycleSpec::where('entity_id', $entity->id)->value('engine_cc'))->toBe(160);
});

test('public entity page exposes specs for a smartphone entity', function () {
    $category = Category::factory()->create(['slug' => 'smartphone']);
    $entity = Entity::factory()->create(['category_id' => $category->id, 'type' => EntityType::Product]);
    SmartphoneSpec::factory()->create([
        'entity_id' => $entity->id,
        'chipset' => 'Snapdragon 7 Gen 4',
        'ram' => '8/12 GB',
    ]);

    $this->get("/e/{$entity->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Entities/Show')
            ->where('specs.title', 'Spesifikasi Smartphone')
            ->where('specs.items', fn ($items) => collect($items)->contains(
                fn ($item) => $item['label'] === 'Chipset' && $item['value'] === 'Snapdragon 7 Gen 4'
            ))
        );
});

test('public entity page has null specs when no detail spec exists', function () {
    $entity = Entity::factory()->create(['type' => EntityType::Brand]);

    $this->get("/e/{$entity->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Entities/Show')
            ->where('specs', null)
        );
});
