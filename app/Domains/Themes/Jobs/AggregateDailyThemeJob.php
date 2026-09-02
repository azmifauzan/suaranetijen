<?php

namespace App\Domains\Themes\Jobs;

use App\Domains\Themes\Services\ThemeAggregator;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AggregateDailyThemeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
