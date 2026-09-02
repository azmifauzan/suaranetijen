<?php

use App\Domains\Ingestion\Jobs\DiscoverSourceDocumentsJob;
use App\Domains\Ingestion\Jobs\PreflightSourceJob;
use App\Domains\Sources\Commands\BackfillSourcesCommand;
use App\Domains\Sources\Commands\PreflightSourcesCommand;
use App\Domains\Sources\Models\Source;
use Illuminate\Support\Facades\Queue;

it('dispatches backfill only for enabled operational sources', function () {
    Queue::fake([DiscoverSourceDocumentsJob::class]);
    $source = Source::factory()->create(['key' => 'diskusiwebhosting']);
    Source::factory()->create(['key' => 'disabled', 'enabled' => false]);

    $this->artisan(BackfillSourcesCommand::class)->assertSuccessful();

    Queue::assertPushed(DiscoverSourceDocumentsJob::class, function (DiscoverSourceDocumentsJob $job) use ($source): bool {
        return $job->source->is($source);
    });
    expect(Queue::pushed(DiscoverSourceDocumentsJob::class))->toHaveCount(1);
});

it('dispatches preflight for every enabled source including blocked sources', function () {
    Queue::fake([PreflightSourceJob::class]);
    $healthy = Source::factory()->create(['key' => 'healthy']);
    $blocked = Source::factory()->create(['key' => 'blocked', 'health_state' => 'blocked']);
    Source::factory()->create(['key' => 'disabled', 'enabled' => false]);

    $this->artisan(PreflightSourcesCommand::class)->assertSuccessful();

    Queue::assertPushed(PreflightSourceJob::class, 2);
    Queue::assertPushed(PreflightSourceJob::class, function (PreflightSourceJob $job) use ($healthy, $blocked): bool {
        return in_array($job->source->id, [$healthy->id, $blocked->id], true);
    });
});
