<?php

use App\Domains\Entities\Enums\CategoryStatus;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Themes\Models\EntityThemeDaily;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Services\TopThemesService;
use Carbon\Carbon;

test('PRD AC 11: entity with fewer than 30 qualified opinions shows empty-state copy, not empty or padded themes list', function () {
    $category = Category::create(['name' => 'VPS', 'slug' => 'vps', 'status' => CategoryStatus::Active]);
    $entity = Entity::create([
        'category_id' => $category->id,
        'type' => EntityType::Service,
        'name' => 'VPS Biznet Gio',
        'slug' => 'vps-biznet-gio',
        'status' => EntityStatus::Active,
        'searchable' => true,
        'rankable' => true,
    ]);

    // Sentiment snapshot with 15 opinions (< 30 threshold)
    SentimentSnapshot::create([
        'entity_id' => $entity->id,
        'period' => Period::OneYear->value,
        'positive_count' => 10,
        'neutral_count' => 3,
        'negative_count' => 2,
        'opinion_count' => 15,
        'score' => null,
        'calculated_at' => now(),
    ]);

    $service = new TopThemesService;
    $result = $service->getTopThemesForEntity($entity, Period::OneYear);

    expect($result['has_enough_data'])->toBeFalse()
        ->and($result['empty_state_message'])->toBe('Belum cukup opini untuk merangkum Suara Netijen.')
        ->and($result['top_themes'])->toBeEmpty()
        ->and($result['positive_themes'])->toBeEmpty()
        ->and($result['negative_themes'])->toBeEmpty();
});

test('PRD AC 11: entity with >= 30 opinions but themes with < 3 occurrences shows empty-state copy and hides thin themes', function () {
    $category = Category::create(['name' => 'VPS', 'slug' => 'vps', 'status' => CategoryStatus::Active]);
    $entity = Entity::create([
        'category_id' => $category->id,
        'type' => EntityType::Service,
        'name' => 'VPS Biznet Gio',
        'slug' => 'vps-biznet-gio',
        'status' => EntityStatus::Active,
        'searchable' => true,
        'rankable' => true,
    ]);

    // 40 opinions (>= 30 qualified threshold)
    SentimentSnapshot::create([
        'entity_id' => $entity->id,
        'period' => Period::OneYear->value,
        'positive_count' => 30,
        'neutral_count' => 5,
        'negative_count' => 5,
        'opinion_count' => 40,
        'score' => 81.25,
        'calculated_at' => now(),
    ]);

    $themeCepat = Theme::create(['slug' => 'cepat', 'display_label' => 'Cepat', 'canonical_key' => 'speed_fast']);

    // But theme only has 2 occurrences (< 3 threshold)
    EntityThemeDaily::create([
        'entity_id' => $entity->id,
        'theme_id' => $themeCepat->id,
        'date' => Carbon::today()->format('Y-m-d'),
        'positive_count' => 2,
        'neutral_count' => 0,
        'negative_count' => 0,
        'observation_count' => 2,
    ]);

    $service = new TopThemesService;
    $result = $service->getTopThemesForEntity($entity, Period::OneYear);

    expect($result['has_enough_data'])->toBeFalse()
        ->and($result['empty_state_message'])->toBe('Belum cukup opini untuk merangkum Suara Netijen.')
        ->and($result['top_themes'])->toBeEmpty();
});

test('entity with >= 30 opinions and themes with >= 3 occurrences displays Top 5 themes and positive/negative groups', function () {
    $category = Category::create(['name' => 'VPS', 'slug' => 'vps', 'status' => CategoryStatus::Active]);
    $entity = Entity::create([
        'category_id' => $category->id,
        'type' => EntityType::Service,
        'name' => 'VPS Biznet Gio',
        'slug' => 'vps-biznet-gio',
        'status' => EntityStatus::Active,
        'searchable' => true,
        'rankable' => true,
    ]);

    SentimentSnapshot::create([
        'entity_id' => $entity->id,
        'period' => Period::OneYear->value,
        'positive_count' => 350,
        'neutral_count' => 50,
        'negative_count' => 100,
        'opinion_count' => 500,
        'score' => 75.0,
        'calculated_at' => now(),
    ]);

    // Create 6 themes (testing Top 5 limit)
    $themes = [
        ['slug' => 'cepat', 'label' => 'Cepat', 'key' => 'speed_fast', 'count' => 150, 'pos' => 140, 'neg' => 5],
        ['slug' => 'handal', 'label' => 'Handal', 'key' => 'reliability_reliable', 'count' => 120, 'pos' => 110, 'neg' => 5],
        ['slug' => 'murah', 'label' => 'Murah', 'key' => 'price_affordable', 'count' => 90, 'pos' => 85, 'neg' => 2],
        ['slug' => 'support-lambat', 'label' => 'Support Lambat', 'key' => 'support_slow', 'count' => 50, 'pos' => 5, 'neg' => 40],
        ['slug' => 'sering-error', 'label' => 'Sering Error', 'key' => 'reliability_unreliable', 'count' => 20, 'pos' => 1, 'neg' => 18],
        ['slug' => 'mahal', 'label' => 'Mahal', 'key' => 'price_expensive', 'count' => 10, 'pos' => 0, 'neg' => 9],
    ];

    $today = Carbon::today()->format('Y-m-d');

    foreach ($themes as $t) {
        $themeModel = Theme::create([
            'slug' => $t['slug'],
            'display_label' => $t['label'],
            'canonical_key' => $t['key'],
        ]);

        EntityThemeDaily::create([
            'entity_id' => $entity->id,
            'theme_id' => $themeModel->id,
            'date' => $today,
            'positive_count' => $t['pos'],
            'neutral_count' => $t['count'] - $t['pos'] - $t['neg'],
            'negative_count' => $t['neg'],
            'observation_count' => $t['count'],
        ]);
    }

    $service = new TopThemesService;
    $result = $service->getTopThemesForEntity($entity, Period::OneYear);

    expect($result['has_enough_data'])->toBeTrue()
        ->and($result['empty_state_message'])->toBeNull()
        ->and($result['top_themes'])->toHaveCount(5) // Top 5 default
        ->and($result['top_themes'][0]['display_label'])->toBe('Cepat')
        ->and($result['top_themes'][0]['observation_count'])->toBe(150)
        ->and($result['top_themes'][1]['display_label'])->toBe('Handal')
        ->and($result['top_themes'][2]['display_label'])->toBe('Murah')
        ->and($result['positive_themes'])->toHaveCount(3)
        ->and($result['positive_themes'][0]['display_label'])->toBe('Cepat')
        ->and($result['negative_themes'])->toHaveCount(3)
        ->and($result['negative_themes'][0]['display_label'])->toBe('Support Lambat');
});
