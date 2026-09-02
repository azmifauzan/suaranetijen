<?php

namespace App\Domains\Themes\Jobs;

use App\Domains\Themes\Services\ThemeAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshThemeSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
