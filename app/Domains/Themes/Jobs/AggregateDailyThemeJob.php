<?php

namespace App\Domains\Themes\Jobs;

use App\Domains\Themes\Services\ThemeAggregator;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AggregateDailyThemeJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The aggregate is recomputed from observations at run time, so a burst of
     * upserts for one entity/day collapses into one pending job.
     */
    public int $uniqueFor = 300;

    public function uniqueId(): string
    {
        return $this->entityId.':'.$this->date->format('Y-m-d');
    }

    public function __construct(
        public int $entityId,
        public CarbonInterface $date
    ) {
        $this->onQueue('aggregate');
    }

    public function handle(ThemeAggregator $aggregator): void
    {
        $aggregator->aggregateDaily($this->entityId, $this->date);
    }
}
