<?php

namespace App\Domains\Themes\Jobs;

use App\Domains\Themes\Services\ThemeAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshThemeSnapshotJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Snapshots are recomputed from the database at run time, so a burst of
     * upserts for one entity collapses into one pending job.
     */
    public int $uniqueFor = 300;

    public function uniqueId(): string
    {
        return (string) $this->entityId;
    }

    public function __construct(
        public int $entityId
    ) {
        $this->onQueue('aggregate');
    }

    public function handle(ThemeAggregator $aggregator): void
    {
        $aggregator->refreshAllSnapshots($this->entityId);
    }
}
