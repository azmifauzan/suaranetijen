<?php

namespace App\Domains\Themes\Jobs;

use App\Domains\Entities\Models\Entity;
use App\Domains\Themes\Services\EntityThemeSummarizer;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SummarizeEntityThemesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $maxExceptions = 3;

    public int $timeout = 140;

    public function retryUntil(): CarbonInterface
    {
        return now()->addHours(24);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(public int $entityId)
    {
        $this->onQueue('aggregate');
    }

    public function handle(EntityThemeSummarizer $summarizer): void
    {
        $entity = Entity::query()->find($this->entityId);
        if ($entity !== null) {
            try {
                $summarizer->summarize($entity);
            } catch (RequestException $e) {
                if ($e->response->status() !== 429) {
                    throw $e;
                }

                $this->release(max(1, (int) $e->response->header('Retry-After') ?: 60));
            }
        }
    }
}
