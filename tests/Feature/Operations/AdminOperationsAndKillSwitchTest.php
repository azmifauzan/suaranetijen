<?php

use App\Domains\Ingestion\Jobs\DiscoverSourceDocumentsJob;
use App\Domains\Ingestion\Jobs\FetchSourceDocumentJob;
use App\Domains\Sources\Enums\DocumentState;
use App\Domains\Sources\Enums\ProcessingState;
use App\Domains\Sources\Enums\SourceHealthState;
use App\Domains\Sources\Enums\SourceType;
use App\Domains\Sources\Models\IngestionFailure;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceDocument;
use App\Domains\Sources\Models\SourceItem;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can view sources and toggle kill switch', function () {
    $admin = User::factory()->admin()->create();

    $source = Source::query()->create([
        'key' => 'serayamotor',
        'name' => 'SerayaMotor',
        'adapter' => 'App\Domains\Sources\Adapters\SerayaMotorAdapter',
        'source_type' => SourceType::Forum,
        'health_state' => SourceHealthState::Healthy,
        'enabled' => true,
        'priority' => 10,
    ]);

    // Admin index
    $this->actingAs($admin)
        ->get('/admin/sources')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Sources/Index')
            ->has('sources', 1)
        );

    // Toggle kill switch (disable)
    $this->actingAs($admin)
        ->post("/admin/sources/{$source->id}/toggle-status")
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => "Source 'SerayaMotor' has been disabled."]);

    expect($source->fresh()->enabled)->toBeFalse();

    // Toggle kill switch (re-enable)
    $this->actingAs($admin)
        ->post("/admin/sources/{$source->id}/toggle-status")
        ->assertRedirect();

    expect($source->fresh()->enabled)->toBeTrue();
});

test('ingestion jobs abort immediately when source is disabled by kill switch', function () {
    Queue::fake();

    $source = Source::query()->create([
        'key' => 'indoforum',
        'name' => 'IndoForum',
        'adapter' => 'App\Domains\Sources\Adapters\IndoForumAdapter',
        'source_type' => SourceType::Forum,
        'health_state' => SourceHealthState::Healthy,
        'enabled' => false, // Kill-switched!
        'priority' => 10,
    ]);

    $doc = SourceDocument::query()->create([
        'source_id' => $source->id,
        'external_id' => 'doc-123',
        'url_hash' => hash('sha256', 'https://example.com/test'),
        'canonical_url' => 'https://example.com/test',
        'processing_state' => ProcessingState::Pending,
    ]);

    // FetchSourceDocumentJob should exit early without fetching or dispatching Extract
    $job = new FetchSourceDocumentJob($doc);
    app()->call([$job, 'handle']);

    Queue::assertNothingPushed();
    expect($doc->fresh()->state)->not->toBe(DocumentState::Fetching);
});

test('admin can view crawl states, ingestion failures, and unmatched mentions', function () {
    $admin = User::factory()->admin()->create();

    $source = Source::query()->create([
        'key' => 'diskusiwebhosting',
        'name' => 'DiskusiWebHosting',
        'adapter' => 'App\Domains\Sources\Adapters\DiskusiWebHostingAdapter',
        'source_type' => SourceType::Forum,
        'health_state' => SourceHealthState::Healthy,
        'enabled' => true,
        'priority' => 10,
    ]);

    IngestionFailure::record(
        sourceId: $source->id,
        stage: 'extract',
        error: new RuntimeException('Test parse fail')
    );

    $this->actingAs($admin)
        ->get('/admin/operations/crawl-states')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Operations/CrawlStates'));

    $this->actingAs($admin)
        ->get('/admin/operations/ingestion-failures')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Operations/IngestionFailures')
            ->has('failures.data', 1)
        );

    $this->actingAs($admin)
        ->get('/admin/operations/unmatched-mentions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Operations/UnmatchedMentions'));
});

test('admin can retry failed ingestion failure and mark it resolved', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();

    $source = Source::query()->create([
        'key' => 'bluesky',
        'name' => 'Bluesky',
        'adapter' => 'App\Domains\Sources\Adapters\BlueskyAdapter',
        'source_type' => SourceType::Social,
        'health_state' => SourceHealthState::Healthy,
        'enabled' => true,
        'priority' => 10,
    ]);

    $item = SourceItem::query()->create([
        'source_id' => $source->id,
        'external_id' => 'at://did:plc:123/app.bsky.feed.post/456',
        'content_hash' => hash('sha256', 'sample content'),
        'processing_state' => ProcessingState::Failed,
    ]);

    $failure = IngestionFailure::record(
        sourceId: $source->id,
        stage: 'classify',
        error: 'Payload missing',
        itemId: $item->id
    );

    expect($failure->resolved_at)->toBeNull();

    $this->actingAs($admin)
        ->post("/admin/operations/failures/{$failure->id}/retry")
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => "Failure #{$failure->id} replayed and marked resolved."]);

    expect($failure->fresh()->resolved_at)->not->toBeNull();
});

test('admin can retry a discovery-stage failure with no document or item yet', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();

    $source = Source::query()->create([
        'key' => 'lowendtalk',
        'name' => 'LowEndTalk',
        'adapter' => 'App\Domains\Sources\Adapters\LowEndTalkAdapter',
        'source_type' => SourceType::Forum,
        'health_state' => SourceHealthState::Healthy,
        'enabled' => true,
        'priority' => 10,
    ]);

    $failure = IngestionFailure::record(
        sourceId: $source->id,
        stage: 'discovery',
        error: new RuntimeException('Discovery request failed')
    );

    $this->actingAs($admin)
        ->post("/admin/operations/failures/{$failure->id}/retry")
        ->assertRedirect();

    Queue::assertPushed(DiscoverSourceDocumentsJob::class, fn (DiscoverSourceDocumentsJob $job) => $job->source->is($source));
    expect($failure->fresh()->resolved_at)->not->toBeNull();
});

test('backup and monitor metrics commands execute successfully', function () {
    $this->artisan('backup:database')
        ->assertExitCode(0);

    $this->artisan('backup:database', ['--verify' => true])
        ->assertExitCode(0);

    $this->artisan('monitor:metrics')
        ->assertExitCode(0);
});
