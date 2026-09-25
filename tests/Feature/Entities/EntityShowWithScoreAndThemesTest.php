<?php

use App\Domains\Entities\Enums\CategoryStatus;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Themes\Models\EntityThemeDaily;
use App\Domains\Themes\Models\EntityThemeSummary;
use App\Domains\Themes\Models\Theme;
use Carbon\Carbon;

test('entity show page surfaces Sentimen Netijen, distribution, and Top Suara Netijen', function () {
    $category = Category::create([
        'name' => 'Hosting',
        'slug' => 'hosting',
        'status' => CategoryStatus::Active,
    ]);

    $entity = Entity::create([
        'category_id' => $category->id,
        'type' => EntityType::Brand,
        'name' => 'IDCloudHost',
        'slug' => 'idcloudhost',
        'status' => EntityStatus::Active,
        'searchable' => true,
        'rankable' => true,
    ]);

    // Sentiment Snapshot (100 opinions, score 70.0)
    SentimentSnapshot::create([
        'entity_id' => $entity->id,
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

    // Themes
    $themeCepat = Theme::create(['slug' => 'cepat', 'display_label' => 'Cepat', 'canonical_key' => 'speed_fast']);
    $themeMurah = Theme::create(['slug' => 'murah', 'display_label' => 'Murah', 'canonical_key' => 'price_affordable']);

    $today = Carbon::today()->format('Y-m-d');
    EntityThemeDaily::create([
        'entity_id' => $entity->id,
        'theme_id' => $themeCepat->id,
        'date' => $today,
        'positive_count' => 40,
        'neutral_count' => 5,
        'negative_count' => 1,
        'observation_count' => 46,
    ]);
    EntityThemeDaily::create([
        'entity_id' => $entity->id,
        'theme_id' => $themeMurah->id,
        'date' => $today,
        'positive_count' => 30,
        'neutral_count' => 2,
        'negative_count' => 0,
        'observation_count' => 32,
    ]);

    $response = $this->get("/e/{$entity->slug}");
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Entities/Show')
        ->where('entity.name', 'IDCloudHost')
        ->where('sentiment.is_eligible', true)
        ->where('sentiment.score', 70)
        ->where('sentiment.opinion_count', 100)
        ->where('sentiment.distribution.positive_pct', 60)
        ->where('sentiment.distribution.neutral_pct', 20)
        ->where('sentiment.distribution.negative_pct', 20)
        ->where('themes.has_enough_data', true)
        ->has('themes.top_themes', 2)
        ->where('themes.top_themes.0.display_label', 'Cepat')
        ->where('themes.top_themes.0.observation_count', 46)
        ->where('themes.top_themes.1.display_label', 'Murah')
        ->where('themes.top_themes.1.observation_count', 32)
    );
});

test('entity show page supports period switching parameter', function () {
    $category = Category::create([
        'name' => 'Hosting',
        'slug' => 'hosting',
        'status' => CategoryStatus::Active,
    ]);

    $entity = Entity::create([
        'category_id' => $category->id,
        'type' => EntityType::Brand,
        'name' => 'Niagahoster',
        'slug' => 'niagahoster',
        'status' => EntityStatus::Active,
        'searchable' => true,
        'rankable' => true,
    ]);

    // 30d snapshot: 40 opinions, score 80
    SentimentSnapshot::create([
        'entity_id' => $entity->id,
        'period' => Period::ThirtyDays->value,
        'positive_count' => 30,
        'neutral_count' => 5,
        'negative_count' => 5,
        'opinion_count' => 40,
        'score' => 81.25,
        'calculated_at' => now(),
    ]);

    // 365d snapshot: 200 opinions, score 65
    SentimentSnapshot::create([
        'entity_id' => $entity->id,
        'period' => Period::OneYear->value,
        'positive_count' => 100,
        'neutral_count' => 60,
        'negative_count' => 40,
        'opinion_count' => 200,
        'score' => 65.0,
        'calculated_at' => now(),
    ]);

    // Requesting 30d period explicitly
    $response30d = $this->get("/e/{$entity->slug}?period=30d");
    $response30d->assertOk();
    $response30d->assertInertia(fn ($page) => $page
        ->where('period', '30d')
        ->where('sentiment.opinion_count', 40)
        ->where('sentiment.score', 81.25)
    );

    // Requesting 365d period explicitly
    $response365d = $this->get("/e/{$entity->slug}?period=365d");
    $response365d->assertOk();
    $response365d->assertInertia(fn ($page) => $page
        ->where('period', '365d')
        ->where('sentiment.opinion_count', 200)
        ->where('sentiment.score', 65)
    );
});

test('entity page exposes the theme summary and per-theme notes for the default period', function () {
    $category = Category::create([
        'name' => 'Hosting',
        'slug' => 'hosting',
        'status' => CategoryStatus::Active,
    ]);

    $entity = Entity::create([
        'category_id' => $category->id,
        'type' => EntityType::Brand,
        'name' => 'IDCloudHost',
        'slug' => 'idcloudhost',
        'status' => EntityStatus::Active,
        'searchable' => true,
        'rankable' => true,
    ]);

    SentimentSnapshot::create([
        'entity_id' => $entity->id,
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

    $themeCepat = Theme::create(['slug' => 'cepat', 'display_label' => 'Cepat', 'canonical_key' => 'speed_fast']);
    $themeMurah = Theme::create(['slug' => 'murah', 'display_label' => 'Murah', 'canonical_key' => 'price_affordable']);

    $today = Carbon::today()->format('Y-m-d');
    EntityThemeDaily::create([
        'entity_id' => $entity->id,
        'theme_id' => $themeCepat->id,
        'date' => $today,
        'positive_count' => 40,
        'neutral_count' => 5,
        'negative_count' => 1,
        'observation_count' => 46,
    ]);
    EntityThemeDaily::create([
        'entity_id' => $entity->id,
        'theme_id' => $themeMurah->id,
        'date' => $today,
        'positive_count' => 30,
        'neutral_count' => 2,
        'negative_count' => 0,
        'observation_count' => 32,
    ]);

    EntityThemeSummary::create([
        'entity_id' => $entity->id,
        'summary' => 'Netizen banyak memuji kecepatan server.',
        'theme_notes' => [$themeCepat->id => 'Halaman dan panel terasa responsif.'],
        'opinion_count' => 100,
        'generated_at' => now(),
    ]);

    $this->get("/e/{$entity->slug}")->assertInertia(fn ($page) => $page
        ->where('themes.summary.text', 'Netizen banyak memuji kecepatan server.')
        ->where('themes.summary.opinion_count', 100)
        ->where('themes.top_themes.0.note', 'Halaman dan panel terasa responsif.')
        ->where('themes.top_themes.1.note', null));
});

test('theme summary is hidden for non-default periods', function () {
    $category = Category::create([
        'name' => 'Hosting',
        'slug' => 'hosting',
        'status' => CategoryStatus::Active,
    ]);

    $entity = Entity::create([
        'category_id' => $category->id,
        'type' => EntityType::Brand,
        'name' => 'Niagahoster',
        'slug' => 'niagahoster',
        'status' => EntityStatus::Active,
        'searchable' => true,
        'rankable' => true,
    ]);

    SentimentSnapshot::create([
        'entity_id' => $entity->id,
        'period' => Period::ThirtyDays->value,
        'positive_count' => 30,
        'neutral_count' => 5,
        'negative_count' => 5,
        'opinion_count' => 40,
        'score' => 81.25,
        'calculated_at' => now(),
    ]);

    $theme = Theme::create(['slug' => 'cepat', 'display_label' => 'Cepat', 'canonical_key' => 'speed_fast']);
    EntityThemeDaily::create([
        'entity_id' => $entity->id,
        'theme_id' => $theme->id,
        'date' => Carbon::today()->format('Y-m-d'),
        'positive_count' => 20,
        'neutral_count' => 0,
        'negative_count' => 0,
        'observation_count' => 20,
    ]);

    EntityThemeSummary::create([
        'entity_id' => $entity->id,
        'summary' => 'Netizen banyak memuji kecepatan server.',
        'theme_notes' => [$theme->id => 'Halaman dan panel terasa responsif.'],
        'opinion_count' => 100,
        'generated_at' => now(),
    ]);

    $this->get("/e/{$entity->slug}?period=30d")->assertInertia(fn ($page) => $page
        ->where('themes.summary', null));
});
