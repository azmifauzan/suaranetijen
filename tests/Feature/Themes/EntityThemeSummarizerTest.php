<?php

use App\Domains\Entities\Models\LlmSetting;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\EntityThemeSummary;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Models\ThemeObservation;
use App\Domains\Themes\Services\EntityThemeSummarizer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function summaryFixture(): array
{
    [$entity, $source, , $llm] = themeModeFixture();
    config(['themes.extractor' => 'llm']);
    SentimentSnapshot::create([
        'entity_id' => $entity->id, 'period' => Period::OneYear->value, 'positive_count' => 20,
        'neutral_count' => 5, 'negative_count' => 15, 'opinion_count' => 40, 'score' => 56.25,
        'sentiment_model_version' => 'v1', 'score_formula_version' => 'v1', 'calculated_at' => now(),
    ]);
    EntityThemeSnapshot::create([
        'entity_id' => $entity->id, 'theme_id' => $llm->id, 'window' => Period::OneYear,
        'observation_count' => 12, 'positive_count' => 1, 'neutral_count' => 0, 'negative_count' => 11,
        'rank' => 1, 'calculated_at' => now(),
    ]);
    ThemeObservation::create([
        'entity_id' => $entity->id, 'theme_id' => $llm->id, 'source_id' => $source->id,
        'sentiment' => SentimentClass::Negative, 'extractor' => 'llm',
        'context' => 'Baterai terasa boros setelah pembaruan sistem.',
    ]);
    LlmSetting::create([
        'base_url' => 'https://llm.test/v1', 'model' => 'm', 'api_key' => 'k',
        'max_tokens' => 800, 'temperature' => 0.2, 'timeout_seconds' => 30,
    ]);

    return [$entity, $llm];
}

function fakeSummaryLlm(array $payload): void
{
    Http::preventStrayRequests();
    Http::fake(['llm.test/*' => Http::response([
        'choices' => [['message' => ['content' => json_encode($payload)]]],
    ])]);
}

it('stores a grounded summary and notes for provided themes only', function () {
    [$entity, $llm] = summaryFixture();
    fakeSummaryLlm([
        'summary' => 'Netizen paling sering mengeluhkan baterai yang cepat habis setelah pembaruan sistem.',
        'theme_notes' => [
            ['theme_id' => $llm->id, 'note' => 'Keluhan baterai boros muncul setelah update.'],
            ['theme_id' => 99999, 'note' => 'Tema karangan.'],
        ],
    ]);

    $summary = app(EntityThemeSummarizer::class)->summarize($entity);

    expect($summary->summary)->toContain('baterai')
        ->and($summary->theme_notes)->toBe([$llm->id => 'Keluhan baterai boros muncul setelah update.'])
        ->and($summary->opinion_count)->toBe(40);
    Http::assertSent(fn (Request $r) => str_contains($r['messages'][1]['content'], 'Baterai terasa boros'));
});

it('keeps the previous summary when the new one breaks copy rules', function (string $bad) {
    [$entity] = summaryFixture();
    $previous = EntityThemeSummary::create([
        'entity_id' => $entity->id, 'summary' => 'Ringkasan lama.', 'theme_notes' => [],
        'opinion_count' => 1, 'generated_at' => now()->subDays(10),
    ]);
    fakeSummaryLlm(['summary' => $bad, 'theme_notes' => []]);

    app(EntityThemeSummarizer::class)->summarize($entity);

    expect($previous->fresh()->summary)->toBe('Ringkasan lama.');
})->with([
    '73% netizen bilang baterai boros.',
    'Sebanyak 73 persen netizen bilang baterai boros.',
    'Samsung adalah HP terbaik di Indonesia.',
    '',
]);

it('skips regeneration when the summary is fresh and opinion count barely moved', function () {
    [$entity] = summaryFixture();
    EntityThemeSummary::create([
        'entity_id' => $entity->id, 'summary' => 'Masih segar.', 'theme_notes' => [],
        'opinion_count' => 38, 'generated_at' => now()->subDay(),
    ]);
    Http::preventStrayRequests();
    Http::fake();

    app(EntityThemeSummarizer::class)->summarize($entity);

    Http::assertNothingSent();
});

it('returns null below the theme threshold', function () {
    [$entity] = summaryFixture();
    SentimentSnapshot::query()->update(['opinion_count' => 5]);
    Http::preventStrayRequests();
    Http::fake();

    expect(app(EntityThemeSummarizer::class)->summarize($entity))->toBeNull();
});

it('gives every top theme its own context examples in the prompt', function () {
    [$entity, $llm] = summaryFixture();
    $second = Theme::create(['slug' => 'kamera-bagus', 'display_label' => 'Kamera bagus', 'canonical_key' => 'kamera-bagus']);
    EntityThemeSnapshot::create([
        'entity_id' => $entity->id, 'theme_id' => $second->id, 'window' => Period::OneYear,
        'observation_count' => 5, 'positive_count' => 5, 'neutral_count' => 0, 'negative_count' => 0,
        'rank' => 2, 'calculated_at' => now(),
    ]);
    $sourceId = ThemeObservation::first()->source_id;
    ThemeObservation::create([
        'entity_id' => $entity->id, 'theme_id' => $second->id, 'source_id' => $sourceId,
        'sentiment' => SentimentClass::Positive, 'extractor' => 'llm', 'context' => 'Kamera malam terasa tajam.',
    ]);
    // Newer rows of the first theme must not crowd the second theme's example out.
    foreach (range(1, 30) as $i) {
        ThemeObservation::create([
            'entity_id' => $entity->id, 'theme_id' => $llm->id, 'source_id' => $sourceId,
            'sentiment' => SentimentClass::Negative, 'extractor' => 'llm', 'context' => "Baterai boros kasus {$i}.",
        ]);
    }
    fakeSummaryLlm(['summary' => 'Ringkasan singkat.', 'theme_notes' => []]);

    app(EntityThemeSummarizer::class)->summarize($entity);

    Http::assertSent(fn (Request $r) => str_contains($r['messages'][1]['content'], 'Kamera malam terasa tajam.'));
});
