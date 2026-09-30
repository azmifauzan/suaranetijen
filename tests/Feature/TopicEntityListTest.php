<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Search\Services\TopicEntityList;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\Theme;

test('it orders entities by mention count desc, score desc nulls last, then name asc', function () {
    $category = Category::factory()->create();
    $theme = Theme::create([
        'slug' => 'murah',
        'display_label' => 'Murah',
        'canonical_key' => 'murah',
    ]);

    $topic = SearchLandingPage::factory()->published()->create([
        'category_id' => $category->id,
    ]);
    $topic->themes()->attach($theme->id);

    // Entity A: 10 mentions, score null (not eligible: < 30 opinions)
    $entityA = Entity::factory()->create([
        'category_id' => $category->id,
        'name' => 'Alpha Cloud',
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);
    EntityThemeSnapshot::create([
        'entity_id' => $entityA->id,
        'theme_id' => $theme->id,
        'window' => Period::OneYear,
        'observation_count' => 10,
        'positive_count' => 5,
        'neutral_count' => 5,
        'negative_count' => 0,
        'rank' => 1,
        'calculated_at' => now(),
    ]);

    // Entity B: 20 mentions, score 80
    $entityB = Entity::factory()->create([
        'category_id' => $category->id,
        'name' => 'Beta Cloud',
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);
    EntityThemeSnapshot::create([
        'entity_id' => $entityB->id,
        'theme_id' => $theme->id,
        'window' => Period::OneYear,
        'observation_count' => 20,
        'positive_count' => 15,
        'neutral_count' => 5,
        'negative_count' => 0,
        'rank' => 1,
        'calculated_at' => now(),
    ]);
    SentimentSnapshot::create([
        'entity_id' => $entityB->id,
        'period' => Period::OneYear,
        'opinion_count' => 50,
        'positive_count' => 40,
        'neutral_count' => 0,
        'negative_count' => 10,
        'score' => 80.0,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    // Entity C: 10 mentions, score 75
    $entityC = Entity::factory()->create([
        'category_id' => $category->id,
        'name' => 'Charlie Cloud',
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);
    EntityThemeSnapshot::create([
        'entity_id' => $entityC->id,
        'theme_id' => $theme->id,
        'window' => Period::OneYear,
        'observation_count' => 10,
        'positive_count' => 8,
        'neutral_count' => 2,
        'negative_count' => 0,
        'rank' => 1,
        'calculated_at' => now(),
    ]);
    SentimentSnapshot::create([
        'entity_id' => $entityC->id,
        'period' => Period::OneYear,
        'opinion_count' => 40,
        'positive_count' => 30,
        'neutral_count' => 0,
        'negative_count' => 10,
        'score' => 75.0,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    // Entity D: 10 mentions, score 90
    $entityD = Entity::factory()->create([
        'category_id' => $category->id,
        'name' => 'Delta Cloud',
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);
    EntityThemeSnapshot::create([
        'entity_id' => $entityD->id,
        'theme_id' => $theme->id,
        'window' => Period::OneYear,
        'observation_count' => 10,
        'positive_count' => 9,
        'neutral_count' => 1,
        'negative_count' => 0,
        'rank' => 1,
        'calculated_at' => now(),
    ]);
    SentimentSnapshot::create([
        'entity_id' => $entityD->id,
        'period' => Period::OneYear,
        'opinion_count' => 40,
        'positive_count' => 36,
        'neutral_count' => 0,
        'negative_count' => 4,
        'score' => 90.0,
        'sentiment_model_version' => 'v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    $service = new TopicEntityList;
    $result = $service->get($topic, useCache: false);

    expect($result['is_indexable'])->toBeTrue()
        ->and($result['window'])->toBe('365d')
        ->and(count($result['entities']))->toBe(4);

    // Check order:
    // 1st: Beta Cloud (20 mentions)
    // 2nd: Delta Cloud (10 mentions, score 90)
    // 3rd: Charlie Cloud (10 mentions, score 75)
    // 4th: Alpha Cloud (10 mentions, score null)
    expect($result['entities'][0]['name'])->toBe('Beta Cloud')
        ->and($result['entities'][1]['name'])->toBe('Delta Cloud')
        ->and($result['entities'][2]['name'])->toBe('Charlie Cloud')
        ->and($result['entities'][3]['name'])->toBe('Alpha Cloud')
        ->and($result['entities'][3]['score'])->toBeNull();

    // Verify no per-theme scores exist in breakdown
    foreach ($result['entities'] as $entityItem) {
        foreach ($entityItem['theme_breakdown'] as $themeItem) {
            expect($themeItem)->toHaveKeys(['theme_id', 'display_label', 'mention_count'])
                ->and(array_key_exists('score', $themeItem))->toBeFalse();
        }
    }
});

test('it falls back from 365d to all window when 365d is below threshold', function () {
    $category = Category::factory()->create();
    $theme = Theme::create([
        'slug' => 'cepat',
        'display_label' => 'Cepat',
        'canonical_key' => 'cepat',
    ]);

    $topic = SearchLandingPage::factory()->published()->create([
        'category_id' => $category->id,
    ]);
    $topic->themes()->attach($theme->id);

    // In 365d: only 1 entity with 5 mentions (threshold requires >= 3 entities)
    $entity1 = Entity::factory()->create(['category_id' => $category->id, 'status' => EntityStatus::Active, 'searchable' => true]);
    EntityThemeSnapshot::create([
        'entity_id' => $entity1->id,
        'theme_id' => $theme->id,
        'window' => Period::OneYear,
        'observation_count' => 5,
        'positive_count' => 5,
        'neutral_count' => 0,
        'negative_count' => 0,
        'rank' => 1,
        'calculated_at' => now(),
    ]);

    // In All window: 3 entities with >= 3 mentions
    $entity2 = Entity::factory()->create(['category_id' => $category->id, 'status' => EntityStatus::Active, 'searchable' => true]);
    $entity3 = Entity::factory()->create(['category_id' => $category->id, 'status' => EntityStatus::Active, 'searchable' => true]);

    foreach ([$entity1, $entity2, $entity3] as $entity) {
        EntityThemeSnapshot::create([
            'entity_id' => $entity->id,
            'theme_id' => $theme->id,
            'window' => Period::All,
            'observation_count' => 5,
            'positive_count' => 5,
            'neutral_count' => 0,
            'negative_count' => 0,
            'rank' => 1,
            'calculated_at' => now(),
        ]);
    }

    $service = new TopicEntityList;
    $result = $service->get($topic, useCache: false);

    expect($result['window'])->toBe('all')
        ->and($result['is_indexable'])->toBeTrue()
        ->and(count($result['entities']))->toBe(3);
});

test('it excludes inactive and non-searchable entities', function () {
    $category = Category::factory()->create();
    $theme = Theme::create([
        'slug' => 'ramah',
        'display_label' => 'Ramah',
        'canonical_key' => 'ramah',
    ]);

    $topic = SearchLandingPage::factory()->published()->create([
        'category_id' => $category->id,
    ]);
    $topic->themes()->attach($theme->id);

    // Inactive entity
    $inactive = Entity::factory()->create(['category_id' => $category->id, 'status' => EntityStatus::Disabled, 'searchable' => true]);
    EntityThemeSnapshot::create([
        'entity_id' => $inactive->id,
        'theme_id' => $theme->id,
        'window' => Period::OneYear,
        'observation_count' => 10,
        'positive_count' => 10,
        'neutral_count' => 0,
        'negative_count' => 0,
        'rank' => 1,
        'calculated_at' => now(),
    ]);

    // Non-searchable entity
    $nonSearchable = Entity::factory()->create(['category_id' => $category->id, 'status' => EntityStatus::Active, 'searchable' => false]);
    EntityThemeSnapshot::create([
        'entity_id' => $nonSearchable->id,
        'theme_id' => $theme->id,
        'window' => Period::OneYear,
        'observation_count' => 10,
        'positive_count' => 10,
        'neutral_count' => 0,
        'negative_count' => 0,
        'rank' => 1,
        'calculated_at' => now(),
    ]);

    $service = new TopicEntityList;
    $result = $service->get($topic, useCache: false);

    expect($result['entities'])->toBeEmpty();
});

test('it excludes Tokoh Publik category completely', function () {
    $tokohPublik = Category::factory()->create(['name' => 'Tokoh Publik', 'slug' => 'tokoh-publik']);
    $politisi = Category::factory()->create(['name' => 'Politisi', 'slug' => 'politisi', 'parent_id' => $tokohPublik->id]);
    $theme = Theme::create([
        'slug' => 'merakyat',
        'display_label' => 'Merakyat',
        'canonical_key' => 'merakyat',
    ]);

    $topic = SearchLandingPage::factory()->published()->create([
        'category_id' => $politisi->id,
    ]);
    $topic->themes()->attach($theme->id);

    $entity = Entity::factory()->create(['category_id' => $politisi->id, 'status' => EntityStatus::Active, 'searchable' => true]);
    EntityThemeSnapshot::create([
        'entity_id' => $entity->id,
        'theme_id' => $theme->id,
        'window' => Period::OneYear,
        'observation_count' => 10,
        'positive_count' => 10,
        'neutral_count' => 0,
        'negative_count' => 0,
        'rank' => 1,
        'calculated_at' => now(),
    ]);

    $service = new TopicEntityList;
    $result = $service->get($topic, useCache: false);

    expect($result['entities'])->toBeEmpty()
        ->and($result['is_indexable'])->toBeFalse();
});
