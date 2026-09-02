<?php

use App\Domains\Entities\Enums\CategoryStatus;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Sentiment\Services\ScoreCalculator;

test('PRD AC 4: score is deterministically recomputable from aggregate counts matching docs/11 worked example', function () {
    // docs/11 worked example: 60 positive, 20 neutral, 20 negative = 70.0
    $score = ScoreCalculator::calculate(60, 20, 20);
    expect($score)->toBe(70.0);

    // Another deterministic check: 100 pos, 0 neu, 0 neg = 100.0
    expect(ScoreCalculator::calculate(100, 0, 0))->toBe(100.0);

    // 0 pos, 100 neu, 0 neg = 50.0
    expect(ScoreCalculator::calculate(0, 100, 0))->toBe(50.0);

    // 0 pos, 0 neu, 100 neg = 0.0
    expect(ScoreCalculator::calculate(0, 0, 100))->toBe(0.0);
});

test('PRD AC 3: entity with >= 30 opinions is public score eligible, while < 30 opinions is not', function () {
    expect(ScoreCalculator::isPublicScoreEligible(30))->toBeTrue()
        ->and(ScoreCalculator::isPublicScoreEligible(31))->toBeTrue()
        ->and(ScoreCalculator::isPublicScoreEligible(29))->toBeFalse()
        ->and(ScoreCalculator::isPublicScoreEligible(0))->toBeFalse();
});

test('PRD AC 3: public entity endpoint /e/{slug} reflects public score when opinions >= 30 and hides when < 30', function () {
    $category = Category::create(['name' => 'VPS', 'slug' => 'vps', 'status' => CategoryStatus::Active]);

    $eligibleEntity = Entity::create([
        'category_id' => $category->id,
        'type' => EntityType::Service,
        'name' => 'VPS Biznet Gio',
        'slug' => 'vps-biznet-gio',
        'status' => EntityStatus::Active,
        'searchable' => true,
        'rankable' => true,
    ]);

    SentimentSnapshot::create([
        'entity_id' => $eligibleEntity->id,
        'period' => Period::OneYear->value,
        'positive_count' => 60,
        'neutral_count' => 20,
        'negative_count' => 20,
        'opinion_count' => 100,
        'score' => 70.0,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    $ineligibleEntity = Entity::create([
        'category_id' => $category->id,
        'type' => EntityType::Service,
        'name' => 'VPS Baru',
        'slug' => 'vps-baru',
        'status' => EntityStatus::Active,
        'searchable' => true,
        'rankable' => true,
    ]);

    SentimentSnapshot::create([
        'entity_id' => $ineligibleEntity->id,
        'period' => Period::OneYear->value,
        'positive_count' => 15,
        'neutral_count' => 5,
        'negative_count' => 2,
        'opinion_count' => 22, // < 30 opinions
        'score' => null,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    // Eligible entity response
    $responseEligible = $this->get("/e/{$eligibleEntity->slug}");
    $responseEligible->assertOk();
    $responseEligible->assertInertia(fn ($page) => $page
        ->component('Entities/Show')
        ->where('sentiment.is_eligible', true)
        ->where('sentiment.score', 70)
        ->where('sentiment.opinion_count', 100)
    );

    // Ineligible entity response: score is null, is_eligible is false
    $responseIneligible = $this->get("/e/{$ineligibleEntity->slug}");
    $responseIneligible->assertOk();
    $responseIneligible->assertInertia(fn ($page) => $page
        ->component('Entities/Show')
        ->where('sentiment.is_eligible', false)
        ->where('sentiment.score', null)
        ->has('sentiment.empty_state_message')
    );
});
