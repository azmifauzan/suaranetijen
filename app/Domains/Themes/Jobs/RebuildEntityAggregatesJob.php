<?php

namespace App\Domains\Themes\Jobs;

use App\Domains\Entities\Services\EntityAggregateRebuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Rebuilds an entity's aggregates once after a burst of off-topic removals. Dispatched with a delay and
 * unique until it starts, so removals from consecutive batches collapse into one rebuild.
 */
class RebuildEntityAggregatesJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public int $uniqueFor = 600;

    public function __construct(public int $entityId)
    {
        $this->onQueue('aggregate');
    }

    public function uniqueId(): string
    {
        return (string) $this->entityId;
    }

    public function handle(EntityAggregateRebuilder $rebuilder): void
    {
        $rebuilder->rebuild($this->entityId);
        SummarizeEntityThemesJob::dispatch($this->entityId);
    }
}
