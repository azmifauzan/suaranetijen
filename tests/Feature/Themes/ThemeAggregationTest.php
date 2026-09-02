<?php

use App\Domains\Entities\Enums\CategoryStatus;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sources\Enums\SourceHealthState;
use App\Domains\Sources\Enums\SourceType;
use App\Domains\Sources\Models\Source;
use App\Domains\Themes\Models\EntityThemeDaily;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Models\ThemeObservation;
use App\Domains\Themes\Services\ThemeAggregator;
use Carbon\Carbon;

test('ThemeAggregator aggregateDaily correctly summarizes positive, neutral, and negative counts', function () {
    $category = Category::create([
        'name' => 'VPS',
        'slug' => 'vps',
        'status' => CategoryStatus::Active,
    ]);

    $entity = Entity::create([
        'category_id' => $category->id,
        'type' => EntityType::Service,
        'name' => 'VPS Biznet Gio',
        'slug' => 'vps-biznet-gio',
        'status' => EntityStatus::Active,
        'searchable' => true,
        'rankable' => true,
    ]);

    $source = Source::create([
        'key' => 'dwh',
        'name' => 'DWH',
        'adapter' => 'DiskusiWebHostingAdapter',
        'source_type' => SourceType::Forum,
        'enabled' => true,
        'priority' => 10,
        'health_state' => SourceHealthState::Healthy,
    ]);

    $themeCepat = Theme::create([
        'slug' => 'cepat',
        'display_label' => 'Cepat',
        'canonical_key' => 'speed_fast',
    ]);

    $today = Carbon::today();

    // 5 positive, 2 neutral, 1 negative observation for theme Cepat
    for ($i = 0; $i < 5; $i++) {
        ThemeObservation::create([
            'entity_id' => $entity->id,
            'theme_id' => $themeCepat->id,
            'source_id' => $source->id,
            'sentiment' => SentimentClass::Positive,
            'confidence' => 0.9,
            'created_at' => $today,
        ]);
    }
    for ($i = 0; $i < 2; $i++) {
        ThemeObservation::create([
            'entity_id' => $entity->id,
            'theme_id' => $themeCepat->id,
            'source_id' => $source->id,
            'sentiment' => SentimentClass::Neutral,
            'confidence' => 0.8,
            'created_at' => $today,
        ]);
    }
    ThemeObservation::create([
        'entity_id' => $entity->id,
        'theme_id' => $themeCepat->id,
        'source_id' => $source->id,
        'sentiment' => SentimentClass::Negative,
        'confidence' => 0.85,
        'created_at' => $today,
    ]);

    $aggregator = new ThemeAggregator;
    $aggregator->aggregateDaily($entity->id, $today);

    $daily = EntityThemeDaily::where('entity_id', $entity->id)
        ->where('theme_id', $themeCepat->id)
        ->whereDate('date', $today->format('Y-m-d'))
        ->first();

    expect($daily)->not->toBeNull()
        ->and($daily->observation_count)->toBe(8)
        ->and($daily->positive_count)->toBe(5)
        ->and($daily->neutral_count)->toBe(2)
        ->and($daily->negative_count)->toBe(1);
});

test('ThemeAggregator aggregateSnapshot ranks themes by total observation count descending', function () {
    $category = Category::create([
        'name' => 'VPS',
        'slug' => 'vps',
        'status' => CategoryStatus::Active,
    ]);

    $entity = Entity::create([
        'category_id' => $category->id,
        'type' => EntityType::Service,
        'name' => 'VPS Biznet Gio',
        'slug' => 'vps-biznet-gio',
        'status' => EntityStatus::Active,
        'searchable' => true,
        'rankable' => true,
    ]);

    $themeCepat = Theme::create(['slug' => 'cepat', 'display_label' => 'Cepat', 'canonical_key' => 'speed_fast']);
    $themeMurah = Theme::create(['slug' => 'murah', 'display_label' => 'Murah', 'canonical_key' => 'price_affordable']);
    $themeHandal = Theme::create(['slug' => 'handal', 'display_label' => 'Handal', 'canonical_key' => 'reliability_reliable']);

    $today = Carbon::today()->format('Y-m-d');

    // Murah: 50, Cepat: 30, Handal: 10
    EntityThemeDaily::create([
        'entity_id' => $entity->id,
        'theme_id' => $themeMurah->id,
        'date' => $today,
        'positive_count' => 45,
        'neutral_count' => 5,
        'negative_count' => 0,
        'observation_count' => 50,
    ]);

    EntityThemeDaily::create([
        'entity_id' => $entity->id,
        'theme_id' => $themeCepat->id,
        'date' => $today,
        'positive_count' => 28,
        'neutral_count' => 2,
        'negative_count' => 0,
        'observation_count' => 30,
    ]);

    EntityThemeDaily::create([
        'entity_id' => $entity->id,
        'theme_id' => $themeHandal->id,
        'date' => $today,
        'positive_count' => 9,
        'neutral_count' => 1,
        'negative_count' => 0,
        'observation_count' => 10,
    ]);

    $aggregator = new ThemeAggregator;
    $aggregator->aggregateSnapshot($entity->id, Period::OneYear);

    $snapshots = EntityThemeSnapshot::where('entity_id', $entity->id)
        ->where('window', Period::OneYear)
        ->orderBy('rank')
        ->get();

    expect($snapshots)->toHaveCount(3)
        ->and($snapshots[0]->theme_id)->toBe($themeMurah->id)
        ->and($snapshots[0]->rank)->toBe(1)
        ->and($snapshots[0]->observation_count)->toBe(50)
        ->and($snapshots[1]->theme_id)->toBe($themeCepat->id)
        ->and($snapshots[1]->rank)->toBe(2)
        ->and($snapshots[1]->observation_count)->toBe(30)
        ->and($snapshots[2]->theme_id)->toBe($themeHandal->id)
        ->and($snapshots[2]->rank)->toBe(3)
        ->and($snapshots[2]->observation_count)->toBe(10);
});
