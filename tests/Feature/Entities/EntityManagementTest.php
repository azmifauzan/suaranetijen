<?php

use App\Domains\Entities\Enums\AliasType;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Models\User;

test('admin can view entities list with filters', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();
    Entity::factory()->count(3)->create(['category_id' => $category->id]);

    $this->actingAs($admin)
        ->get("/admin/entities?category_id={$category->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Entities/Index')
            ->has('entities.data', 3)
            ->has('categories')
            ->has('parent_brands')
        );
});

test('admin can paginate through entities list and customize per page', function () {
    $admin = User::factory()->admin()->create();
    Entity::factory()->count(35)->create();

    $this->actingAs($admin)
        ->get('/admin/entities?page=2&per_page=10')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Entities/Index')
            ->has('entities.data', 10)
            ->where('entities.current_page', 2)
            ->where('entities.per_page', 10)
            ->where('entities.total', 35)
            ->where('entities.last_page', 4)
            ->where('filters.per_page', 10)
            ->has('entities.prev_page_url')
            ->has('entities.next_page_url')
            ->has('entities.links')
        );
});

test('admin can create entity with automatic primary alias', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();

    $this->actingAs($admin)
        ->post('/admin/entities', [
            'name' => 'Biznet Gio',
            'slug' => 'biznet-gio',
            'type' => 'brand',
            'category_id' => $category->id,
            'description' => 'Cloud provider Indonesia',
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Entity created successfully.']);

    $entity = Entity::where('slug', 'biznet-gio')->first();
    expect($entity)->not->toBeNull()
        ->and($entity->type)->toBe(EntityType::Brand)
        ->and($entity->status)->toBe(EntityStatus::Active);

    $this->assertDatabaseHas('entity_aliases', [
        'entity_id' => $entity->id,
        'alias' => 'Biznet Gio',
        'normalized_alias' => 'biznet gio',
        'alias_type' => AliasType::Primary->value,
    ]);
});

test('admin can update entity', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();
    $entity = Entity::factory()->create([
        'category_id' => $category->id,
        'name' => 'Original Entity',
        'slug' => 'original-entity',
    ]);

    $this->actingAs($admin)
        ->put("/admin/entities/{$entity->id}", [
            'name' => 'Updated Entity',
            'slug' => 'updated-entity',
            'type' => 'product',
            'category_id' => $category->id,
            'description' => 'Updated description',
            'status' => 'active',
            'searchable' => true,
            'rankable' => true,
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Entity updated successfully.']);

    $this->assertDatabaseHas('entities', [
        'id' => $entity->id,
        'name' => 'Updated Entity',
        'slug' => 'updated-entity',
    ]);
});

test('admin can toggle entity status for disable-without-delete', function () {
    $admin = User::factory()->admin()->create();
    $entity = Entity::factory()->create(['status' => EntityStatus::Active]);

    $this->actingAs($admin)
        ->post("/admin/entities/{$entity->id}/toggle-status")
        ->assertRedirect();

    expect($entity->fresh()->status)->toBe(EntityStatus::Disabled);

    $this->actingAs($admin)
        ->post("/admin/entities/{$entity->id}/toggle-status")
        ->assertRedirect();

    expect($entity->fresh()->status)->toBe(EntityStatus::Active);
});

test('admin can add and remove aliases for an entity', function () {
    $admin = User::factory()->admin()->create();
    $entity = Entity::factory()->create();

    $this->actingAs($admin)
        ->post("/admin/entities/{$entity->id}/aliases", [
            'alias' => 'Galaxy S24 Ultra 5G',
            'alias_type' => 'common_variant',
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Alias added successfully.']);

    $this->assertDatabaseHas('entity_aliases', [
        'entity_id' => $entity->id,
        'alias' => 'Galaxy S24 Ultra 5G',
        'normalized_alias' => 'galaxy s24 ultra 5g',
    ]);

    $alias = EntityAlias::where('entity_id', $entity->id)->where('normalized_alias', 'galaxy s24 ultra 5g')->first();

    $this->actingAs($admin)
        ->delete("/admin/entities/{$entity->id}/aliases/{$alias->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('entity_aliases', [
        'id' => $alias->id,
    ]);
});

test('duplicate normalized aliases are rejected for the same entity', function () {
    $admin = User::factory()->admin()->create();
    $entity = Entity::factory()->create();

    EntityAlias::factory()->create([
        'entity_id' => $entity->id,
        'alias' => 'Samsung A57',
        'normalized_alias' => 'samsung a57',
    ]);

    $this->actingAs($admin)
        ->post("/admin/entities/{$entity->id}/aliases", [
            'alias' => '  Samsung A57!  ',
        ])
        ->assertSessionHasErrors('alias');
});
