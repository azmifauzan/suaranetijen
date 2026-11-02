<?php

use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->storageDir = sys_get_temp_dir().'/metrics-test-'.uniqid();
    $this->app->useStoragePath($this->storageDir);
});

afterEach(function () {
    File::deleteDirectory($this->storageDir);
});

test('monitor flags an operational source with no new items in 48 hours', function () {
    $this->freezeTime();
    $stalled = Source::factory()->create(['name' => 'Kaskus Macet']);
    SourceItem::factory()->create(['source_id' => $stalled->id, 'created_at' => now()->subHours(49)]);
    $producing = Source::factory()->create(['name' => 'Detik Jalan']);
    SourceItem::factory()->create(['source_id' => $producing->id, 'created_at' => now()->subHours(47)]);
    Source::factory()->create(['name' => 'Sumber Mati', 'enabled' => false]);

    $this->artisan('monitor:metrics')
        ->expectsOutputToContain('Source Kaskus Macet produced no new items in 48h')
        ->doesntExpectOutputToContain('Source Detik Jalan produced no new items')
        ->doesntExpectOutputToContain('Source Sumber Mati produced no new items')
        ->assertSuccessful();
});

test('monitor flags a missing or day-old backup and accepts a fresh one', function () {
    $this->artisan('monitor:metrics', ['--fail-on-breach' => true])
        ->expectsOutputToContain('No database backup found')
        ->assertFailed();

    $backup = storage_path('app/backups/db_backup_2026-11-01_020000.enc');
    File::ensureDirectoryExists(dirname($backup));
    File::put($backup, 'x');
    touch($backup, now()->subHours(27)->timestamp);

    $this->artisan('monitor:metrics')
        ->expectsOutputToContain('Latest database backup is 27h old')
        ->assertSuccessful();

    touch($backup, now()->subHours(2)->timestamp);

    $this->artisan('monitor:metrics')
        ->doesntExpectOutputToContain('database backup')
        ->assertSuccessful();
});

test('monitor reads queue depth and oldest job age from the queue connection', function () {
    $this->freezeTime();
    Queue::shouldReceive('pendingSize')->andReturnUsing(fn (string $queue) => $queue === 'crawl' ? 1200 : 0);
    Queue::shouldReceive('creationTimeOfOldestPendingJob')
        ->andReturnUsing(fn (string $queue) => $queue === 'aggregate' ? now()->subSeconds(4000)->timestamp : null);

    $this->artisan('monitor:metrics')
        ->expectsOutputToContain('Queue depth high: 1200 pending jobs')
        ->expectsOutputToContain('Oldest job age critical: 4000s in queue')
        ->assertSuccessful();
});
