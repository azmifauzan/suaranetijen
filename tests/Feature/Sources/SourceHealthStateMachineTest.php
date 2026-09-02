<?php

use App\Domains\Ingestion\Jobs\DiscoverSourceDocumentsJob;
use App\Domains\Ingestion\Jobs\PreflightSourceJob;
use App\Domains\Sources\Adapters\FakeSourceAdapter;
use App\Domains\Sources\Contracts\SourceHealth;
use App\Domains\Sources\Enums\SourceHealthState;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourcePreflightLog;
use App\Domains\Sources\Services\SourceRateLimiter;
use App\Domains\Sources\Services\SourceRegistry;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    FakeSourceAdapter::reset();
});

test('PreflightSourceJob logs success and updates source health to healthy', function () {
    $source = Source::factory()->create([
        'adapter' => 'App\\Domains\\Sources\\Adapters\\FakeSourceAdapter',
        'health_state' => SourceHealthState::Degraded,
    ]);

    FakeSourceAdapter::setHealth(SourceHealth::healthy('All systems green', 45));

    app(PreflightSourceJob::class, ['source' => $source])->handle(
        app(SourceRegistry::class)
    );

    $source->refresh();
    expect($source->health_state)->toBe(SourceHealthState::Healthy)
        ->and($source->last_preflight_at)->not->toBeNull();

    $log = SourcePreflightLog::where('source_id', $source->id)->latest('created_at')->first();
    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(SourceHealthState::Healthy)
        ->and($log->response_time_ms)->toBe(45);
});

test('PreflightSourceJob records blocked state when adapter reports blocked or throws', function () {
    $source = Source::factory()->create([
        'adapter' => 'App\\Domains\\Sources\\Adapters\\FakeSourceAdapter',
        'health_state' => SourceHealthState::Healthy,
    ]);

    FakeSourceAdapter::setHealth(SourceHealth::blocked('IP rate limited or captcha challenge detected', 500));

    app(PreflightSourceJob::class, ['source' => $source])->handle(
        app(SourceRegistry::class)
    );

    $source->refresh();
    expect($source->health_state)->toBe(SourceHealthState::Blocked);

    $log = SourcePreflightLog::where('source_id', $source->id)->latest('created_at')->first();
    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(SourceHealthState::Blocked)
        ->and($log->message)->toContain('captcha challenge');
});

test('non-operational source is safely skipped by crawler discovery job', function () {
    Queue::fake();

    $blockedSource = Source::factory()->create([
        'adapter' => 'App\\Domains\\Sources\\Adapters\\FakeSourceAdapter',
        'health_state' => SourceHealthState::Blocked,
    ]);

    app(DiscoverSourceDocumentsJob::class, ['source' => $blockedSource])->handle(
        app(SourceRegistry::class),
        app(SourceRateLimiter::class)
    );

    // No documents should be created or fetched
    Queue::assertNothingPushed();
    expect($blockedSource->documents()->count())->toBe(0);
});
