<?php

use App\Domains\Entities\Enums\CategoryStatus;
use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sources\Enums\SourceHealthState;
use App\Domains\Sources\Enums\SourceType;
use App\Domains\Sources\Models\Source;
use App\Domains\Themes\Jobs\AggregateDailyThemeJob;
use App\Domains\Themes\Jobs\ExtractThemesJob;
use App\Domains\Themes\Jobs\RefreshThemeSnapshotJob;
use App\Domains\Themes\Jobs\UpsertThemeObservationJob;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Models\ThemeAlias;
use App\Domains\Themes\Models\ThemeObservation;
use App\Domains\Themes\Services\ThemeExtractor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;

test('ExtractThemesJob extracts themes and dispatches UpsertThemeObservationJob on analysis queue', function () {
    Queue::fake();

    $theme = Theme::create([
        'slug' => 'cepat',
        'display_label' => 'Cepat',
        'canonical_key' => 'speed_fast',
    ]);

    ThemeAlias::create([
        'theme_id' => $theme->id,
        'alias' => 'ngebut',
        'normalized_alias' => 'ngebut',
    ]);

    $job = new ExtractThemesJob(
        entityId: 1,
        sourceId: 2,
        sourceItemId: 3,
        text: 'Servernya sangat ngebut sekali!',
        sourceDocumentHash: 'doc-hash-123'
    );

    expect($job->queue)->toBe('analysis');

    $job->handle(app(ThemeExtractor::class));

    Queue::assertPushed(UpsertThemeObservationJob::class, function ($pushedJob) use ($theme) {
        return $pushedJob->entityId === 1
            && $pushedJob->themeId === $theme->id
            && $pushedJob->sourceId === 2
            && $pushedJob->sourceItemId === 3
            && $pushedJob->sentiment === SentimentClass::Positive
            && $pushedJob->queue === 'analysis';
    });
});

test('UpsertThemeObservationJob creates observation idempotently', function () {
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

    $source = Source::create([
        'key' => 'dwh',
        'name' => 'DWH',
        'adapter' => 'DiskusiWebHostingAdapter',
        'source_type' => SourceType::Forum,
        'enabled' => true,
        'priority' => 10,
        'health_state' => SourceHealthState::Healthy,
    ]);

    $theme = Theme::create(['slug' => 'cepat', 'display_label' => 'Cepat', 'canonical_key' => 'speed_fast']);

    Queue::fake();

    $job = new UpsertThemeObservationJob(
        entityId: $entity->id,
        themeId: $theme->id,
        sourceId: $source->id,
        sourceItemId: null,
        sourceDocumentHash: 'hash-abc',
        sentiment: SentimentClass::Positive,
        confidence: 0.95
    );

    $obs = $job->handle();

    expect($obs)->toBeInstanceOf(ThemeObservation::class)
        ->and($obs->entity_id)->toBe($entity->id)
        ->and($obs->theme_id)->toBe($theme->id)
        ->and($obs->sentiment)->toBe(SentimentClass::Positive);

    Queue::assertPushed(AggregateDailyThemeJob::class, fn ($pushedJob) => $pushedJob->entityId === $entity->id);
    Queue::assertPushed(RefreshThemeSnapshotJob::class, fn ($pushedJob) => $pushedJob->entityId === $entity->id);
});

test('AggregateDailyThemeJob and RefreshThemeSnapshotJob are dispatched on aggregate queue', function () {
    $dailyJob = new AggregateDailyThemeJob(1, Carbon::today());
    $snapshotJob = new RefreshThemeSnapshotJob(1);

    expect($dailyJob->queue)->toBe('aggregate')
        ->and($snapshotJob->queue)->toBe('aggregate');
});
