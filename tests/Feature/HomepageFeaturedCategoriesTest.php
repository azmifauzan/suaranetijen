<?php

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;

it('excludes parent taxonomy categories from the homepage, which never carry entities directly', function () {
    $parent = Category::factory()->create(['name' => 'Automotive']);
    $child = Category::factory()->create(['name' => 'Motor', 'parent_id' => $parent->id]);
    Entity::factory()->create(['category_id' => $child->id]);

    $response = $this->get(route('home'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('categories', fn (Collection $categories) => $categories->pluck('name')->doesntContain('Automotive')
            && $categories->pluck('name')->contains('Motor')
        )
    );
});
