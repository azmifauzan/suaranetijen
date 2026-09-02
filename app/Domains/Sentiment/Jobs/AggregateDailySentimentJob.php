<?php

namespace App\Domains\Sentiment\Jobs;

use App\Domains\Sentiment\Services\SentimentAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AggregateDailySentimentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $entityId,
        public string $date
    ) {
        $this->queue = 'aggregate';
    }

    public function handle(SentimentAggregator $aggregator): void
    {
        $parsedDate = CarbonImmutable::parse($this->date);
        $aggregator->aggregateDaily($this->entityId, $parsedDate);
    }
}
