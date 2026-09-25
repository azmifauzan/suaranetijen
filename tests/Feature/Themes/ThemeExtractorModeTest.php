<?php

use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Themes\Jobs\AggregateDailyThemeJob;
use App\Domains\Themes\Jobs\ExtractThemesJob;
use App\Domains\Themes\Jobs\RefreshThemeSnapshotJob;
use App\Domains\Themes\Jobs\UpsertThemeObservationJob;
use App\Domains\Themes\Models\EntityThemeDaily;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\ThemeObservation;
use App\Domains\Themes\Services\LlmThemeExtractor;
use App\Domains\Themes\Services\ThemeAggregator;
use App\Domains\Themes\Services\ThemeExtractor;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

it('only aggregates observations from the active extractor', function () {
    [$entity, $source, $keyword, $llm] = themeModeFixture();
    config(['themes.extractor' => 'llm']);

    foreach ([$keyword, $llm] as $theme) {
        ThemeObservation::create([
            'entity_id' => $entity->id, 'theme_id' => $theme->id, 'source_id' => $source->id,
            'sentiment' => SentimentClass::Negative,
            'extractor' => $theme->is($llm) ? 'llm' : 'keyword',
        ]);
    }

    $aggregator = app(ThemeAggregator::class);
    $aggregator->aggregateDaily($entity->id, CarbonImmutable::now());
    $aggregator->aggregateSnapshot($entity->id, Period::OneYear);

    expect(EntityThemeDaily::pluck('theme_id')->all())->toBe([$llm->id])
        ->and(EntityThemeSnapshot::pluck('theme_id')->all())->toBe([$llm->id]);
});

it('deletes stale daily and snapshot rows for themes that no longer qualify', function () {
    [$entity, $source, $keyword] = themeModeFixture();
    config(['themes.extractor' => 'llm']);

    EntityThemeDaily::create([
        'entity_id' => $entity->id, 'theme_id' => $keyword->id, 'date' => now()->format('Y-m-d'),
        'positive_count' => 5, 'neutral_count' => 0, 'negative_count' => 0, 'observation_count' => 5,
    ]);
    EntityThemeSnapshot::create([
        'entity_id' => $entity->id, 'theme_id' => $keyword->id, 'window' => Period::OneYear,
        'observation_count' => 5, 'positive_count' => 5, 'neutral_count' => 0, 'negative_count' => 0,
        'rank' => 1, 'calculated_at' => now(),
    ]);

    $aggregator = app(ThemeAggregator::class);
    $aggregator->aggregateDaily($entity->id, CarbonImmutable::now());
    $aggregator->aggregateSnapshot($entity->id, Period::OneYear);

    expect(EntityThemeDaily::count())->toBe(0)
        ->and(EntityThemeSnapshot::count())->toBe(0);
});

it('uses the llm extractor and passes extractor + context when configured', function () {
    [$entity, $source, , $llmTheme] = themeModeFixture();
    config(['themes.extractor' => 'llm']);
    Queue::fake();

    $this->mock(LlmThemeExtractor::class)
        ->shouldReceive('extract')->once()->with($entity->id, 'Samsung', 'Baterainya cepat habis sejak update kemarin.')
        ->andReturn([['theme' => $llmTheme, 'sentiment' => SentimentClass::Negative, 'confidence' => 0.8, 'context' => 'Baterai boros.']]);

    (new ExtractThemesJob(entityId: $entity->id, sourceId: $source->id, sourceItemId: null, text: 'Baterainya cepat habis sejak update kemarin.'))
        ->handle(app(ThemeExtractor::class), app(LlmThemeExtractor::class));

    Queue::assertPushed(UpsertThemeObservationJob::class, fn ($job) => $job->extractor === 'llm'
        && $job->context === 'Baterai boros.' && $job->themeId === $llmTheme->id);
});

it('lets an llm failure bubble up so the job retries instead of falling back', function () {
    [$entity, $source] = themeModeFixture();
    config(['themes.extractor' => 'llm']);
    Queue::fake();

    $this->mock(LlmThemeExtractor::class)
        ->shouldReceive('extract')->andThrow(new RuntimeException('llm down'));

    expect(fn () => (new ExtractThemesJob(entityId: $entity->id, sourceId: $source->id, sourceItemId: null, text: 'Baterainya cepat habis sejak update kemarin.'))
        ->handle(app(ThemeExtractor::class), app(LlmThemeExtractor::class)))
        ->toThrow(RuntimeException::class);

    Queue::assertNotPushed(UpsertThemeObservationJob::class);
});

it('does not duplicate observations when the same item is upserted twice', function () {
    [$entity, $source, , $llmTheme] = themeModeFixture();
    Queue::fake();
    $item = SourceItem::factory()->create(['source_id' => $source->id]);

    foreach ([1, 2] as $attempt) {
        (new UpsertThemeObservationJob(
            entityId: $entity->id, themeId: $llmTheme->id, sourceId: $source->id, sourceItemId: $item->id,
            sourceDocumentHash: null, sentiment: SentimentClass::Negative, extractor: 'llm', context: 'Baterai boros.'
        ))->handle();
    }

    expect(ThemeObservation::where('extractor', 'llm')->count())->toBe(1);
});

it('skips llm extraction for very short opinions', function () {
    [$entity, $source] = themeModeFixture();
    config(['themes.extractor' => 'llm']);
    Queue::fake();

    $this->mock(LlmThemeExtractor::class)->shouldNotReceive('extract');

    (new ExtractThemesJob(entityId: $entity->id, sourceId: $source->id, sourceItemId: null, text: 'mantap'))
        ->handle(app(ThemeExtractor::class), app(LlmThemeExtractor::class));

    Queue::assertNothingPushed();
});

it('skips llm extraction for an item that already has llm observations', function () {
    [$entity, $source, , $llmTheme] = themeModeFixture();
    config(['themes.extractor' => 'llm']);
    Queue::fake();
    $item = SourceItem::factory()->create(['source_id' => $source->id]);
    ThemeObservation::create([
        'entity_id' => $entity->id, 'theme_id' => $llmTheme->id, 'source_id' => $source->id,
        'source_item_id' => $item->id, 'sentiment' => SentimentClass::Negative, 'extractor' => 'llm',
    ]);

    $this->mock(LlmThemeExtractor::class)->shouldNotReceive('extract');

    (new ExtractThemesJob(
        entityId: $entity->id, sourceId: $source->id, sourceItemId: $item->id,
        text: 'Baterainya cepat habis sejak update kemarin, kecewa banget.'
    ))->handle(app(ThemeExtractor::class), app(LlmThemeExtractor::class));

    Queue::assertNothingPushed();
});

it('runs theme extraction on its own queue so it never waits behind sentiment jobs', function () {
    expect((new ExtractThemesJob(entityId: 1, sourceId: 1, sourceItemId: null, text: 'x'))->queue)->toBe('themes');
});

it('collapses bursts of aggregate jobs for one entity into a single pending job', function () {
    $day = CarbonImmutable::parse('2026-09-25');

    expect((new AggregateDailyThemeJob(7, $day))->uniqueId())
        ->toBe((new AggregateDailyThemeJob(7, $day->addHours(5)))->uniqueId())
        ->not->toBe((new AggregateDailyThemeJob(8, $day))->uniqueId())
        ->and((new RefreshThemeSnapshotJob(7))->uniqueId())->toBe('7')
        ->and(new RefreshThemeSnapshotJob(7))
        ->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class);
});
