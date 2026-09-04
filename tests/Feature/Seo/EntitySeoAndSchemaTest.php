<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Ratings\Models\RatingSnapshot;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentDaily;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use Inertia\Testing\AssertableInertia as Assert;

test('entity below threshold passes is_eligible false to Show page', function () {
    $category = Category::query()->create([
        'name' => 'Smartphone',
        'slug' => 'smartphone',
        'is_active' => true,
    ]);

    $entity = Entity::query()->create([
        'name' => 'Brand Baru X',
        'slug' => 'brand-baru-x',
        'type' => EntityType::Brand,
        'status' => EntityStatus::Active,
        'category_id' => $category->id,
        'searchable' => true,
        'rankable' => true,
    ]);

    SentimentSnapshot::query()->create([
        'entity_id' => $entity->id,
        'period' => Period::OneYear->value,
        'score' => null,
        'opinion_count' => 15,
        'positive_count' => 10,
        'neutral_count' => 3,
        'negative_count' => 2,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    $this->get("/e/{$entity->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Entities/Show')
            ->where('sentiment.is_eligible', false)
            ->where('sentiment.score', null)
            ->has('trend')
        );
});

test('entity above threshold passes is_eligible true and first-party rating snapshot', function () {
    $category = Category::query()->create([
        'name' => 'Cloud Provider',
        'slug' => 'cloud-provider',
        'is_active' => true,
    ]);

    $entity = Entity::query()->create([
        'name' => 'Biznet Gio',
        'slug' => 'biznet-gio',
        'type' => EntityType::Brand,
        'status' => EntityStatus::Active,
        'category_id' => $category->id,
        'searchable' => true,
        'rankable' => true,
    ]);

    SentimentSnapshot::query()->create([
        'entity_id' => $entity->id,
        'period' => Period::OneYear->value,
        'score' => 88.0,
        'opinion_count' => 50,
        'positive_count' => 40,
        'neutral_count' => 8,
        'negative_count' => 2,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    RatingSnapshot::query()->create([
        'entity_id' => $entity->id,
        'rating_count' => 12,
        'rating_average' => 4.5,
        'distribution' => [
            '1' => 0,
            '2' => 0,
            '3' => 1,
            '4' => 4,
            '5' => 7,
        ],
        'calculated_at' => now(),
    ]);

    SentimentDaily::query()->create([
        'entity_id' => $entity->id,
        'date' => now()->subDay()->toDateString(),
        'score' => 88.0,
        'opinion_count' => 10,
        'positive_count' => 8,
        'neutral_count' => 2,
        'negative_count' => 0,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
    ]);

    $this->get("/e/{$entity->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Entities/Show')
            ->where('sentiment.is_eligible', true)
            ->where('sentiment.score', 88)
            ->where('rating.rating_count', 12)
            ->where('rating.rating_average', 4.5)
            ->has('trend', 1)
        );
});
