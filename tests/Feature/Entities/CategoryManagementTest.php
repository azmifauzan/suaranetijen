<?php

use App\Domains\Entities\Enums\CategoryStatus;
use App\Domains\Entities\Models\Category;
use App\Models\User;

test('admin can view categories list', function () {
    $admin = User::factory()->admin()->create();
    Category::factory()->count(5)->create();

    $this->actingAs($admin)
        ->get('/admin/categories')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Categories/Index')
            ->has('categories.data')
            ->has('parent_categories')
        );
});

test('admin can create category', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/admin/categories', [
            'name' => 'Cloud & Hosting',
            'slug' => 'cloud-hosting',
            'status' => 'active',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('categories', [
        'name' => 'Cloud & Hosting',
        'slug' => 'cloud-hosting',
        'status' => 'active',
    ]);
});

test('admin can update category', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);

    $this->actingAs($admin)
        ->put("/admin/categories/{$category->id}", [
            'name' => 'Updated Name',
            'slug' => 'updated-name',
            'status' => 'active',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Updated Name',
        'slug' => 'updated-name',
    ]);
});

test('admin can toggle category status for disable-without-delete', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create(['status' => CategoryStatus::Active]);

    $this->actingAs($admin)
        ->post("/admin/categories/{$category->id}/toggle-status")
        ->assertRedirect();

    expect($category->fresh()->status)->toBe(CategoryStatus::Disabled);

    $this->actingAs($admin)
        ->post("/admin/categories/{$category->id}/toggle-status")
        ->assertRedirect();

    expect($category->fresh()->status)->toBe(CategoryStatus::Active);
});
