<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Sentiment\Services\SentimentRankingService;

test('Epic 3 DoD: ranking query orders by score desc, opinion_count desc, name asc', function () {
    $category = Category::factory()->create();

    // Entity A: score 70.0, 100 opinions
    $entityA = Entity::factory()->create([
        'name' => 'Entity Alpha',
        'category_id' => $category->id,
        'status' => EntityStatus::Active,
        'rankable' => true,
    ]);
    SentimentSnapshot::factory()->create([
        'entity_id' => $entityA->id,
        'period' => Period::OneYear,
        'positive_count' => 60,
        'neutral_count' => 20,
        'negative_count' => 20,
        'opinion_count' => 100,
        'score' => 70.0,
    ]);

    // Entity B: score 85.0, 120 opinions (Highest score -> should be #1)
    $entityB = Entity::factory()->create([
        'name' => 'Entity Beta',
        'category_id' => $category->id,
        'status' => EntityStatus::Active,
        'rankable' => true,
    ]);
    SentimentSnapshot::factory()->create([
        'entity_id' => $entityB->id,
        'period' => Period::OneYear,
        'positive_count' => 80,
        'neutral_count' => 20,
        'negative_count' => 20,
        'opinion_count' => 120,
        'score' => 85.0,
    ]);

    // Entity C: score 70.0, 150 opinions (Same score as A, but higher opinions -> should be #2, ahead of A)
    $entityC = Entity::factory()->create([
        'name' => 'Entity Charlie',
        'category_id' => $category->id,
        'status' => EntityStatus::Active,
        'rankable' => true,
    ]);
    SentimentSnapshot::factory()->create([
        'entity_id' => $entityC->id,
        'period' => Period::OneYear,
        'positive_count' => 90,
        'neutral_count' => 30,
        'negative_count' => 30,
        'opinion_count' => 150,
        'score' => 70.0,
    ]);

    // Entity D: score 70.0, 100 opinions, name 'Entity AA' (Same score and opinions as A, but name AA < Alpha -> should be #3, ahead of A)
    $entityD = Entity::factory()->create([
        'name' => 'Entity AA',
        'category_id' => $category->id,
        'status' => EntityStatus::Active,
        'rankable' => true,
    ]);
    SentimentSnapshot::factory()->create([
        'entity_id' => $entityD->id,
        'period' => Period::OneYear,
        'positive_count' => 60,
        'neutral_count' => 20,
        'negative_count' => 20,
        'opinion_count' => 100,
        'score' => 70.0,
    ]);

    $rankingService = app(SentimentRankingService::class);
    $ranked = $rankingService->getRanking($category->id, Period::OneYear);

    expect($ranked)->toHaveCount(4);

    // Rank 1: Beta (score 85.0)
    expect($ranked[0]['entity']->id)->toBe($entityB->id)
        ->and($ranked[0]['rank'])->toBe(1)
        ->and($ranked[0]['score'])->toBe(85.0);

    // Rank 2: Charlie (score 70.0, 150 opinions)
    expect($ranked[1]['entity']->id)->toBe($entityC->id)
        ->and($ranked[1]['rank'])->toBe(2)
        ->and($ranked[1]['opinion_count'])->toBe(150);

    // Rank 3: AA (score 70.0, 100 opinions, name AA precedes Alpha)
    expect($ranked[2]['entity']->id)->toBe($entityD->id)
        ->and($ranked[2]['rank'])->toBe(3);

    // Rank 4: Alpha (score 70.0, 100 opinions)
    expect($ranked[3]['entity']->id)->toBe($entityA->id)
        ->and($ranked[3]['rank'])->toBe(4);
});

test('entities with fewer than 100 opinions are excluded from ranking', function () {
    $category = Category::factory()->create();

    $entityUnderThreshold = Entity::factory()->create([
        'category_id' => $category->id,
        'status' => EntityStatus::Active,
        'rankable' => true,
    ]);
    SentimentSnapshot::factory()->create([
        'entity_id' => $entityUnderThreshold->id,
        'period' => Period::OneYear,
        'positive_count' => 80,
        'neutral_count' => 10,
        'negative_count' => 5,
        'opinion_count' => 95, // < 100
        'score' => 89.47,
    ]);

    $rankingService = app(SentimentRankingService::class);
    $ranked = $rankingService->getRanking($category->id, Period::OneYear);

    expect($ranked)->toBeEmpty();
});

test('non-active or non-rankable entities are excluded from ranking', function () {
    $category = Category::factory()->create();

    $inactiveEntity = Entity::factory()->create([
        'category_id' => $category->id,
        'status' => EntityStatus::Disabled,
        'rankable' => true,
    ]);
    SentimentSnapshot::factory()->create([
        'entity_id' => $inactiveEntity->id,
        'period' => Period::OneYear,
        'opinion_count' => 150,
        'score' => 90.0,
    ]);

    $unrankableEntity = Entity::factory()->create([
        'category_id' => $category->id,
        'status' => EntityStatus::Active,
        'rankable' => false,
    ]);
    SentimentSnapshot::factory()->create([
        'entity_id' => $unrankableEntity->id,
        'period' => Period::OneYear,
        'opinion_count' => 150,
        'score' => 90.0,
    ]);

    $rankingService = app(SentimentRankingService::class);
    $ranked = $rankingService->getRanking($category->id, Period::OneYear);

    expect($ranked)->toBeEmpty();
});
