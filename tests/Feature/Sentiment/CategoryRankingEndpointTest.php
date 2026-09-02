<?php

use App\Domains\Entities\Enums\CategoryStatus;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;

test('PRD AC 8: category ranking endpoint orders by score desc without popularity bonus', function () {
    $category = Category::create([
        'name' => 'Cloud VPS',
        'slug' => 'cloud-vps',
        'status' => CategoryStatus::Active,
    ]);

    // Entity A: higher volume (1000 opinions) but lower score (75.0)
    $entityA = Entity::create([
        'category_id' => $category->id,
        'type' => EntityType::Brand,
        'name' => 'Popular Cloud',
        'slug' => 'popular-cloud',
        'status' => EntityStatus::Active,
        'searchable' => true,
        'rankable' => true,
    ]);
    SentimentSnapshot::create([
        'entity_id' => $entityA->id,
        'period' => Period::OneYear->value,
        'positive_count' => 600,
        'neutral_count' => 300,
        'negative_count' => 100,
        'opinion_count' => 1000,
        'score' => 75.0,
        'calculated_at' => now(),
    ]);

    // Entity B: lower volume (150 opinions) but higher score (90.0)
    $entityB = Entity::create([
        'category_id' => $category->id,
        'type' => EntityType::Brand,
        'name' => 'Top Quality Cloud',
        'slug' => 'top-quality-cloud',
        'status' => EntityStatus::Active,
        'searchable' => true,
        'rankable' => true,
    ]);
    SentimentSnapshot::create([
        'entity_id' => $entityB->id,
        'period' => Period::OneYear->value,
        'positive_count' => 130,
        'neutral_count' => 10,
        'negative_count' => 10,
        'opinion_count' => 150,
        'score' => 90.0,
        'calculated_at' => now(),
    ]);

    // Entity C: below ranking threshold (80 opinions < 100 threshold)
    $entityC = Entity::create([
        'category_id' => $category->id,
        'type' => EntityType::Brand,
        'name' => 'Small Cloud',
        'slug' => 'small-cloud',
        'status' => EntityStatus::Active,
        'searchable' => true,
        'rankable' => true,
    ]);
    SentimentSnapshot::create([
        'entity_id' => $entityC->id,
        'period' => Period::OneYear->value,
        'positive_count' => 78,
        'neutral_count' => 2,
        'negative_count' => 0,
        'opinion_count' => 80,
        'score' => 98.75,
        'calculated_at' => now(),
    ]);

    // 1. Web route /top/{slug}
    $response = $this->get("/top/{$category->slug}");
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Top/Show')
        ->where('category.slug', 'cloud-vps')
        ->has('rankings', 2) // Entity C is excluded (< 100 opinions)
        ->where('rankings.0.entity.name', 'Top Quality Cloud') // Higher score ranks 1st despite lower volume
        ->where('rankings.0.rank', 1)
        ->where('rankings.0.score', 90)
        ->where('rankings.1.entity.name', 'Popular Cloud')
        ->where('rankings.1.rank', 2)
        ->where('rankings.1.score', 75)
    );

    // 2. Internal Web API route /api/categories/{slug}/ranking
    $apiResponse = $this->getJson("/api/categories/{$category->slug}/ranking");
    $apiResponse->assertOk()
        ->assertJson([
            'category' => [
                'name' => 'Cloud VPS',
                'slug' => 'cloud-vps',
            ],
            'total' => 2,
        ])
        ->assertJsonPath('data.0.entity.name', 'Top Quality Cloud')
        ->assertJsonPath('data.0.score', 90)
        ->assertJsonPath('data.1.entity.name', 'Popular Cloud')
        ->assertJsonPath('data.1.score', 75);
});

test('category ranking page returns 404 for unknown or non-active category', function () {
    $this->get('/top/non-existent-category')->assertNotFound();
    $this->getJson('/api/categories/non-existent-category/ranking')->assertNotFound();
});
