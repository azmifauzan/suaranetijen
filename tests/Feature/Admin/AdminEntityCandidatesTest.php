<?php

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityCandidate;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can view pending candidates ranked by frequency', function () {
    $admin = User::factory()->admin()->create();
    EntityCandidate::factory()->create(['normalized_term' => 'low', 'frequency_score' => 2]);
    EntityCandidate::factory()->create(['normalized_term' => 'high', 'frequency_score' => 50]);
    EntityCandidate::factory()->approved()->create(['normalized_term' => 'already handled']);

    $this->actingAs($admin)
        ->get('/admin/entity-candidates')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/EntityCandidates/Index')
            ->has('candidates.data', 2)
            ->where('candidates.data.0.normalized_term', 'high')
        );
});

test('approving a candidate creates an entity with a primary alias and marks the candidate approved', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::query()->create(['name' => 'Smartphone', 'slug' => 'smartphone']);
    $candidate = EntityCandidate::factory()->create([
        'normalized_term' => 'iphone 17 pro',
        'suggested_name' => 'iPhone 17 Pro',
    ]);

    $this->actingAs($admin)
        ->post("/admin/entity-candidates/{$candidate->id}/approve", [
            'name' => 'iPhone 17 Pro',
            'entity_type' => 'product',
            'category_id' => $category->id,
            'parent_id' => null,
            'aliases' => ['ip17 pro', 'iphone17pro'],
        ])
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Entity created from candidate.']);

    $candidate->refresh();
    expect($candidate->status)->toBe('approved')
        ->and($candidate->entity_id)->not->toBeNull()
        ->and($candidate->reviewed_by)->toBe($admin->id);

    $entity = Entity::query()->find($candidate->entity_id);
    expect($entity->name)->toBe('iPhone 17 Pro')
        ->and($entity->type->value)->toBe('product')
        ->and($entity->category_id)->toBe($category->id)
        ->and($entity->aliases()->count())->toBe(3);
});

test('rejecting a candidate marks it dismissed without creating an entity', function () {
    $admin = User::factory()->admin()->create();
    $candidate = EntityCandidate::factory()->create();

    $this->actingAs($admin)
        ->post("/admin/entity-candidates/{$candidate->id}/reject")
        ->assertRedirect();

    $candidate->refresh();
    expect($candidate->status)->toBe('rejected')
        ->and($candidate->entity_id)->toBeNull();
});
