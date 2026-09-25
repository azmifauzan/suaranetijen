<?php

use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Themes\Models\EntityThemeDaily;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\ThemeObservation;

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
