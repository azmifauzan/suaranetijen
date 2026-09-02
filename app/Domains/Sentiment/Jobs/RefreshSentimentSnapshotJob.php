<?php

namespace App\Domains\Sentiment\Jobs;

use App\Domains\Sentiment\Services\SentimentAggregator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshSentimentSnapshotJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $entityId
    ) {
        $this->queue = 'aggregate';
    }

    public function handle(SentimentAggregator $aggregator): void
    {
        $aggregator->refreshAllSnapshots($this->entityId);
    }
}
