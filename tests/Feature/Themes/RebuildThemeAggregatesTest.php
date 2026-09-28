<?php

use App\Domains\Entities\Enums\CategoryStatus;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Themes\Models\EntityThemeDaily;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\ThemeObservation;
use App\Domains\Themes\Services\ThemeAggregator;

it('rebuilds aggregates from only the active extractor', function () {
    [$entity, $source, $keyword, $llm] = themeModeFixture();
    config(['themes.extractor' => 'llm']);

    EntityThemeDaily::create([
        'entity_id' => $entity->id, 'theme_id' => $keyword->id, 'date' => now()->format('Y-m-d'),
        'positive_count' => 144, 'neutral_count' => 0, 'negative_count' => 0, 'observation_count' => 144,
    ]);
    ThemeObservation::create([
        'entity_id' => $entity->id, 'theme_id' => $llm->id, 'source_id' => $source->id,
        'sentiment' => SentimentClass::Negative, 'extractor' => 'llm',
    ]);

    $this->artisan('themes:rebuild-aggregates')->assertSuccessful();

    expect(EntityThemeDaily::pluck('theme_id')->all())->toBe([$llm->id])
        ->and(EntityThemeSnapshot::where('window', Period::OneYear)->pluck('theme_id')->all())->toBe([$llm->id]);
});

it('rebuilds each entity in its own transaction so no single transaction holds every entity\'s locks', function () {
    [$firstEntity, $source, , $llm] = themeModeFixture();
    config(['themes.extractor' => 'llm']);
    $category = Category::create(['name' => 'Smartphone 2', 'slug' => 'smartphone-2', 'status' => CategoryStatus::Active]);
    $secondEntity = Entity::create([
        'category_id' => $category->id, 'type' => EntityType::Brand, 'name' => 'Xiaomi', 'slug' => 'xiaomi',
        'status' => EntityStatus::Active, 'searchable' => true, 'rankable' => true,
    ]);
    foreach ([$firstEntity, $secondEntity] as $entity) {
        ThemeObservation::create([
            'entity_id' => $entity->id, 'theme_id' => $llm->id, 'source_id' => $source->id,
            'sentiment' => SentimentClass::Positive, 'extractor' => 'llm',
        ]);
    }

    $this->artisan('themes:rebuild-aggregates')
        ->expectsOutputToContain('Rebuilt theme aggregates for 2 entities')
        ->assertSuccessful();

    expect(EntityThemeSnapshot::where('entity_id', $firstEntity->id)->exists())->toBeTrue()
        ->and(EntityThemeSnapshot::where('entity_id', $secondEntity->id)->exists())->toBeTrue();
});

it('keeps rebuilding other entities when one entity fails, and reports the failure', function () {
    [$firstEntity, $source, , $llm] = themeModeFixture();
    config(['themes.extractor' => 'llm']);
    $category = Category::create(['name' => 'Smartphone 3', 'slug' => 'smartphone-3', 'status' => CategoryStatus::Active]);
    $secondEntity = Entity::create([
        'category_id' => $category->id, 'type' => EntityType::Brand, 'name' => 'Oppo', 'slug' => 'oppo',
        'status' => EntityStatus::Active, 'searchable' => true, 'rankable' => true,
    ]);
    foreach ([$firstEntity, $secondEntity] as $entity) {
        ThemeObservation::create([
            'entity_id' => $entity->id, 'theme_id' => $llm->id, 'source_id' => $source->id,
            'sentiment' => SentimentClass::Positive, 'extractor' => 'llm',
        ]);
    }

    $mock = $this->mock(ThemeAggregator::class);
    $mock->shouldReceive('aggregateDaily')->andReturnUsing(function (int $entityId) use ($firstEntity) {
        if ($entityId === $firstEntity->id) {
            throw new RuntimeException('simulated failure');
        }

        return collect();
    });
    $mock->shouldReceive('refreshAllSnapshots')->withArgs(fn (int $id) => $id !== $firstEntity->id);

    $this->artisan('themes:rebuild-aggregates')
        ->expectsOutputToContain('1 failed')
        ->assertFailed();
});
