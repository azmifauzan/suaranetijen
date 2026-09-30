<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\SmartphoneSpec;
use App\Domains\Search\Models\EntitySearchDocument;
use App\Domains\Search\Services\EntitySearchDocumentBuilder;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\EntityThemeSummary;
use App\Domains\Themes\Models\Theme;

it('builds entity search document populating fields from correct sources and normalizing text', function () {
    $entity = Entity::factory()->create([
        'name' => 'Acme Phone X',
        'description' => 'Smartphone flagship dengan kamera canggih dan baterai besar.',
    ]);

    // Add Smartphone spec
    SmartphoneSpec::create([
        'entity_id' => $entity->id,
        'chipset' => 'Snapdragon 8 Gen 3',
        'ram' => '12GB',
        'storage' => '256GB',
        'battery_mah' => 5000,
        'release_year' => 2024,
    ]);

    // Add themes: 1 valid positive theme, 1 negated theme
    $themeMurah = Theme::create([
        'slug' => 'harga-murah',
        'display_label' => 'Harga Murah',
        'canonical_key' => 'harga-murah',
    ]);
    $themeTidakAwet = Theme::create([
        'slug' => 'tidak-awet',
        'display_label' => 'Tidak Awet',
        'canonical_key' => 'tidak-awet',
    ]);

    EntityThemeSnapshot::create([
        'entity_id' => $entity->id,
        'theme_id' => $themeMurah->id,
        'window' => Period::OneYear,
        'observation_count' => 50,
        'positive_count' => 45,
        'neutral_count' => 5,
        'negative_count' => 0,
        'rank' => 1,
        'calculated_at' => now(),
    ]);

    EntityThemeSnapshot::create([
        'entity_id' => $entity->id,
        'theme_id' => $themeTidakAwet->id,
        'window' => Period::OneYear,
        'observation_count' => 20,
        'positive_count' => 0,
        'neutral_count' => 0,
        'negative_count' => 20,
        'rank' => 2,
        'calculated_at' => now(),
    ]);

    // Add EntityThemeSummary
    EntityThemeSummary::create([
        'entity_id' => $entity->id,
        'summary' => 'Banyak netizen memuji daya tahan baterai dan kecepatan pengisian.',
        'theme_notes' => ['Baterai tahan seharian penuh'],
        'opinion_count' => 100,
        'generated_at' => now(),
    ]);

    $builder = app(EntitySearchDocumentBuilder::class);
    $doc = $builder->buildForEntity($entity);

    expect($doc)->toBeInstanceOf(EntitySearchDocument::class);
    expect($doc->entity_id)->toBe($entity->id);

    // description_text normalized
    expect($doc->description_text)->toContain('smartphone flagship dengan kamera canggih dan baterai besar');

    // spec_text normalized
    expect($doc->spec_text)->toContain('snapdragon 8 gen 3');
    expect($doc->spec_text)->toContain('5000');

    // theme_text: contains 'harga murah', but EXCLUDES 'tidak awet' due to negation marker
    expect($doc->theme_text)->toContain('harga murah');
    expect($doc->theme_text)->not->toContain('tidak awet');

    // summary_text normalized
    expect($doc->summary_text)->toContain('banyak netizen memuji daya tahan baterai');
});

it('handles entity without specs, themes, or summary gracefully producing null/empty text without error', function () {
    $entity = Entity::factory()->create([
        'name' => 'Bare Entity',
        'description' => null,
    ]);

    $builder = app(EntitySearchDocumentBuilder::class);
    $doc = $builder->buildForEntity($entity);

    expect($doc)->toBeInstanceOf(EntitySearchDocument::class);
    expect($doc->entity_id)->toBe($entity->id);
    expect($doc->description_text)->toBeNull();
    expect($doc->spec_text)->toBeNull();
    expect($doc->theme_text)->toBeNull();
    expect($doc->summary_text)->toBeNull();
});

it('falls back to all-time theme snapshots if 365d snapshot does not exist', function () {
    $entity = Entity::factory()->create();

    $theme = Theme::create([
        'slug' => 'desain-elegan',
        'display_label' => 'Desain Elegan',
        'canonical_key' => 'desain-elegan',
    ]);

    EntityThemeSnapshot::create([
        'entity_id' => $entity->id,
        'theme_id' => $theme->id,
        'window' => Period::All,
        'observation_count' => 15,
        'positive_count' => 15,
        'neutral_count' => 0,
        'negative_count' => 0,
        'rank' => 1,
        'calculated_at' => now(),
    ]);

    $builder = app(EntitySearchDocumentBuilder::class);
    $doc = $builder->buildForEntity($entity);

    expect($doc->theme_text)->toContain('desain elegan');
});
