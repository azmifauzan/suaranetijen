<?php

namespace App\Domains\Themes\Jobs;

use App\Domains\Entities\Models\Entity;
use App\Domains\Themes\Services\EntityThemeSummarizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SummarizeEntityThemesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $entityId)
    {
        $this->onQueue('aggregate');
    }

    public function handle(EntityThemeSummarizer $summarizer): void
    {
        $entity = Entity::query()->find($this->entityId);
        if ($entity !== null) {
            $summarizer->summarize($entity);
        }
    }
}
