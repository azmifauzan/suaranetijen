<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\EntityAlias;
use App\Domains\Search\Services\EntitySearchDocumentBuilder;
use App\Domains\Search\Services\SearchService;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\Theme;

it('returns relevant entities for anchor + descriptor query such as "vps murah"', function () {
    $catHosting = Category::factory()->create(['name' => 'Web Hosting & VPS', 'slug' => 'hosting']);

    $vpsMurah = Entity::factory()->create([
        'name' => 'VPS Kilat Express',
        'category_id' => $catHosting->id,
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);

    $vpsMahal = Entity::factory()->create([
        'name' => 'VPS Enterprise Titan',
        'category_id' => $catHosting->id,
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);

    $themeMurah = Theme::create([
        'slug' => 'harga-murah',
        'display_label' => 'Harga Murah',
        'canonical_key' => 'harga-murah',
    ]);

    EntityThemeSnapshot::create([
        'entity_id' => $vpsMurah->id,
        'theme_id' => $themeMurah->id,
        'window' => Period::OneYear,
        'observation_count' => 80,
        'positive_count' => 80,
        'neutral_count' => 0,
        'negative_count' => 0,
        'rank' => 1,
        'calculated_at' => now(),
    ]);

    $builder = app(EntitySearchDocumentBuilder::class);
    $builder->buildForEntity($vpsMurah);
    $builder->buildForEntity($vpsMahal);

    $service = app(SearchService::class);
    $results = $service->search('vps murah');

    expect($results['meta']['total'])->toBeGreaterThanOrEqual(1);
    // VPS Kilat Express should be rank 1 because it matches anchor 'vps' AND descriptor 'murah'
    expect($results['data'][0]['id'])->toBe($vpsMurah->id);
    expect($results['data'][0]['matched_fields'])->toContain('theme:Harga Murah');
});

it('preserves existing behavior for anchor-only queries such as "vps biznet"', function () {
    $category = Category::factory()->create(['name' => 'Hosting', 'slug' => 'hosting']);

    $biznet = Entity::factory()->create([
        'name' => 'Biznet Gio',
        'category_id' => $category->id,
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);

    $vpsBiznet = Entity::factory()->create([
        'name' => 'VPS Biznet Gio',
        'parent_id' => $biznet->id,
        'category_id' => $category->id,
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);

    $service = app(SearchService::class);
    $results = $service->search('vps biznet');

    $ids = array_column($results['data'], 'id');
    expect($ids)->toContain($vpsBiznet->id);
    expect($ids)->toContain($biznet->id);
});

it('returns entities matching descriptor-only queries such as "baterai awet"', function () {
    $category = Category::factory()->create(['name' => 'Smartphone', 'slug' => 'smartphone']);

    $phoneAwet = Entity::factory()->create([
        'name' => 'Galaxy M54',
        'category_id' => $category->id,
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);

    $phoneLain = Entity::factory()->create([
        'name' => 'Pixel Minimal',
        'category_id' => $category->id,
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);

    $themeBaterai = Theme::create([
        'slug' => 'baterai-awet',
        'display_label' => 'Baterai Awet',
        'canonical_key' => 'baterai-awet',
    ]);

    EntityThemeSnapshot::create([
        'entity_id' => $phoneAwet->id,
        'theme_id' => $themeBaterai->id,
        'window' => Period::OneYear,
        'observation_count' => 120,
        'positive_count' => 120,
        'neutral_count' => 0,
        'negative_count' => 0,
        'rank' => 1,
        'calculated_at' => now(),
    ]);

    $builder = app(EntitySearchDocumentBuilder::class);
    $builder->buildForEntity($phoneAwet);
    $builder->buildForEntity($phoneLain);

    $service = app(SearchService::class);
    $results = $service->search('baterai awet');

    expect($results['meta']['total'])->toBeGreaterThanOrEqual(1);
    expect($results['data'][0]['id'])->toBe($phoneAwet->id);
    expect($results['data'][0]['matched_fields'])->toContain('theme:Baterai Awet');
});

it('falls back to browse mode when query consists solely of stopwords or years', function () {
    $category = Category::factory()->create();
    Entity::factory()->count(3)->create(['category_id' => $category->id, 'status' => EntityStatus::Active, 'searchable' => true]);

    $service = app(SearchService::class);

    // Stopword query
    $res1 = $service->search('yang terbaik');
    expect($res1['meta']['total'])->toBe(3);
    expect($res1['data'][0]['priority_tier'])->toBe(SearchService::PRIORITY_BROWSE);

    // Year query
    $res2 = $service->search('2026');
    expect($res2['meta']['total'])->toBe(3);
    expect($res2['data'][0]['priority_tier'])->toBe(SearchService::PRIORITY_BROWSE);
});

it('ensures exact name match always ranks higher than descriptor match', function () {
    $category = Category::factory()->create();

    // Entity 1: Exact name match for "Murah"
    $exactEntity = Entity::factory()->create([
        'name' => 'Murah',
        'category_id' => $category->id,
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);

    // Entity 2: Heavy theme descriptor match for "Murah"
    $themeEntity = Entity::factory()->create([
        'name' => 'Super Phone Ultra',
        'category_id' => $category->id,
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);

    $theme = Theme::create([
        'slug' => 'murah',
        'display_label' => 'Murah Meriah',
        'canonical_key' => 'murah',
    ]);

    EntityThemeSnapshot::create([
        'entity_id' => $themeEntity->id,
        'theme_id' => $theme->id,
        'window' => Period::OneYear,
        'observation_count' => 1000,
        'positive_count' => 1000,
        'neutral_count' => 0,
        'negative_count' => 0,
        'rank' => 1,
        'calculated_at' => now(),
    ]);

    $builder = app(EntitySearchDocumentBuilder::class);
    $builder->buildForEntity($exactEntity);
    $builder->buildForEntity($themeEntity);

    $service = app(SearchService::class);
    $results = $service->search('murah');

    // Exact name MUST rank first
    expect($results['data'][0]['id'])->toBe($exactEntity->id);
    expect($results['data'][0]['priority_tier'])->toBe(SearchService::PRIORITY_EXACT_NAME);
});

it('ranks entities with higher theme observation_count above lower ones for descriptor queries', function () {
    $category = Category::factory()->create();

    $phoneA = Entity::factory()->create([
        'name' => 'Phone Alpha',
        'category_id' => $category->id,
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);

    $phoneB = Entity::factory()->create([
        'name' => 'Phone Beta',
        'category_id' => $category->id,
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);

    $theme = Theme::create([
        'slug' => 'awet',
        'display_label' => 'Awet Banget',
        'canonical_key' => 'awet',
    ]);

    // phoneA has 200 observations
    EntityThemeSnapshot::create([
        'entity_id' => $phoneA->id,
        'theme_id' => $theme->id,
        'window' => Period::OneYear,
        'observation_count' => 200,
        'positive_count' => 200,
        'neutral_count' => 0,
        'negative_count' => 0,
        'rank' => 1,
        'calculated_at' => now(),
    ]);

    // phoneB has 10 observations
    EntityThemeSnapshot::create([
        'entity_id' => $phoneB->id,
        'theme_id' => $theme->id,
        'window' => Period::OneYear,
        'observation_count' => 10,
        'positive_count' => 10,
        'neutral_count' => 0,
        'negative_count' => 0,
        'rank' => 1,
        'calculated_at' => now(),
    ]);

    $builder = app(EntitySearchDocumentBuilder::class);
    $builder->buildForEntity($phoneA);
    $builder->buildForEntity($phoneB);

    $service = app(SearchService::class);
    $results = $service->search('awet');

    expect($results['data'][0]['id'])->toBe($phoneA->id);
    expect($results['data'][1]['id'])->toBe($phoneB->id);
});

it('uses sentiment score as final tie-breaker when textual match scores are equal', function () {
    $category = Category::factory()->create();

    $e1 = Entity::factory()->create(['name' => 'Hosting Alpha', 'category_id' => $category->id, 'status' => EntityStatus::Active, 'searchable' => true]);
    $e2 = Entity::factory()->create(['name' => 'Hosting Beta', 'category_id' => $category->id, 'status' => EntityStatus::Active, 'searchable' => true]);

    $theme = Theme::create(['slug' => 'murah', 'display_label' => 'Murah', 'canonical_key' => 'murah']);

    // Identical observation counts
    EntityThemeSnapshot::create([
        'entity_id' => $e1->id,
        'theme_id' => $theme->id,
        'window' => Period::OneYear,
        'observation_count' => 50,
        'positive_count' => 50,
        'neutral_count' => 0,
        'negative_count' => 0,
        'rank' => 1,
        'calculated_at' => now(),
    ]);
    EntityThemeSnapshot::create([
        'entity_id' => $e2->id,
        'theme_id' => $theme->id,
        'window' => Period::OneYear,
        'observation_count' => 50,
        'positive_count' => 50,
        'neutral_count' => 0,
        'negative_count' => 0,
        'rank' => 1,
        'calculated_at' => now(),
    ]);

    // e2 has higher sentiment score (90 vs 75)
    SentimentSnapshot::factory()->create(['entity_id' => $e1->id, 'period' => Period::OneYear->value, 'score' => 75.0, 'opinion_count' => 50]);
    SentimentSnapshot::factory()->create(['entity_id' => $e2->id, 'period' => Period::OneYear->value, 'score' => 90.0, 'opinion_count' => 50]);

    $builder = app(EntitySearchDocumentBuilder::class);
    $builder->buildForEntity($e1);
    $builder->buildForEntity($e2);

    $service = app(SearchService::class);
    $results = $service->search('murah');

    // e2 should rank above e1 because of higher sentiment score
    expect($results['data'][0]['id'])->toBe($e2->id);
    expect($results['data'][1]['id'])->toBe($e1->id);
});

function activeEntity(string $name, Category $category, array $attributes = []): Entity
{
    return Entity::factory()->create(array_merge([
        'name' => $name,
        'category_id' => $category->id,
        'status' => EntityStatus::Active,
        'searchable' => true,
    ], $attributes));
}

function themeSnapshot(Entity $entity, Theme $theme, int $count = 20): void
{
    EntityThemeSnapshot::create([
        'entity_id' => $entity->id,
        'theme_id' => $theme->id,
        'window' => Period::OneYear,
        'observation_count' => $count,
        'positive_count' => $count,
        'neutral_count' => 0,
        'negative_count' => 0,
        'rank' => 1,
        'calculated_at' => now(),
    ]);
}

it('does not let a below-threshold sentiment score win the tie-break', function () {
    $category = Category::factory()->create();
    $tiny = activeEntity('Hosting Tiny', $category, ['description' => 'layanan murah']);
    $solid = activeEntity('Hosting Solid', $category, ['description' => 'layanan murah']);

    // 100 from a single opinion is noise, not a reason to rank first.
    SentimentSnapshot::factory()->create(['entity_id' => $tiny->id, 'period' => Period::OneYear->value, 'score' => 100.0, 'opinion_count' => 1]);
    SentimentSnapshot::factory()->create(['entity_id' => $solid->id, 'period' => Period::OneYear->value, 'score' => 60.0, 'opinion_count' => 100]);

    $builder = app(EntitySearchDocumentBuilder::class);
    $builder->buildForEntity($tiny);
    $builder->buildForEntity($solid);

    $ids = collect(app(SearchService::class)->search('murah')['data'])->pluck('id')->all();

    expect($ids)->toBe([$solid->id, $tiny->id]);
});

it('ignores aliases of disabled entities when deciding whether a word is an anchor', function () {
    $category = Category::factory()->create(['name' => 'Hosting']);
    $vps = activeEntity('VPS Nusantara Prima Sentosa', $category, ['description' => 'server murah']);
    $gone = activeEntity('Toko Lama', $category, ['status' => EntityStatus::Disabled]);
    EntityAlias::create(['entity_id' => $gone->id, 'alias' => 'murah banget']);

    app(EntitySearchDocumentBuilder::class)->buildForEntity($vps);

    $ids = collect(app(SearchService::class)->search('vps murah')['data'])->pluck('id')->all();

    expect($ids)->toContain($vps->id);
});

it('does not show a negated theme as the reason an entity matched', function () {
    $category = Category::factory()->create();
    $entity = activeEntity('Hosting Mahal', $category, ['description' => 'paket murah untuk pemula']);
    $negated = Theme::create(['slug' => 'tidak-murah', 'display_label' => 'Tidak Murah', 'canonical_key' => 'tidak-murah']);
    themeSnapshot($entity, $negated);

    app(EntitySearchDocumentBuilder::class)->buildForEntity($entity);

    $row = collect(app(SearchService::class)->search('murah')['data'])->firstWhere('id', $entity->id);

    expect($row['matched_fields'])->not->toContain('theme:Tidak Murah');
});

it('lists at most three matching themes per result', function () {
    $category = Category::factory()->create();
    $entity = activeEntity('Hosting Ramai', $category);
    foreach (range(1, 5) as $i) {
        themeSnapshot($entity, Theme::create(['slug' => "murah-{$i}", 'display_label' => "Murah {$i}", 'canonical_key' => "murah-{$i}"]), 10 + $i);
    }
    app(EntitySearchDocumentBuilder::class)->buildForEntity($entity);

    $row = collect(app(SearchService::class)->search('murah')['data'])->firstWhere('id', $entity->id);

    expect(collect($row['matched_fields'])->filter(fn ($f) => str_starts_with($f, 'theme:')))->toHaveCount(3);
});

it('treats single-character words as noise and caps very long queries', function () {
    $category = Category::factory()->create();
    $vps = activeEntity('VPS Kilat', $category);

    $service = app(SearchService::class);

    expect(collect($service->search('a vps')['data'])->pluck('id')->all())->toContain($vps->id);
    expect(fn () => $service->search(implode(' ', array_fill(0, 40, 'vps'))))->not->toThrow(Throwable::class);
});

it('still finds an entity whose search document has not been built yet by its name', function () {
    $category = Category::factory()->create();
    $entity = activeEntity('Niagahoster', $category);

    expect(collect(app(SearchService::class)->search('niagahoster')['data'])->pluck('id')->all())->toContain($entity->id);
});

it('does not treat a short word as an anchor just because it sits inside a longer alias', function () {
    $category = Category::factory()->create();
    $honda = activeEntity('Honda Mobil', $category);
    EntityAlias::create(['entity_id' => $honda->id, 'alias' => 'hpm']);
    $phone = activeEntity('Poco X7', $category, ['description' => 'hp murah dengan chipset kencang']);

    $builder = app(EntitySearchDocumentBuilder::class);
    $builder->buildForEntity($honda);
    $builder->buildForEntity($phone);

    $ids = collect(app(SearchService::class)->search('hp murah')['data'])->pluck('id')->all();

    expect($ids)->toContain($phone->id)->not->toContain($honda->id);
});
