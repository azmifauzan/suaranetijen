<?php

namespace App\Domains\Themes\Jobs;

use App\Domains\Entities\Models\Entity;
use App\Domains\Themes\Services\LlmThemeExtractor;
use App\Domains\Themes\Services\OffTopicOpinionRemover;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

/**
 * Re-judges already stored opinions (from their theme contexts) with the LLM relevance check and
 * removes the ones that are not about the entity. Same rate limiter and 429 handling as extraction.
 */
class RecheckRelevanceBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $maxExceptions = 3;

    /**
     * @param  list<array{sourceItemId: int, text: string}>  $items
     */
    public function __construct(public int $entityId, public array $items)
    {
        $this->onQueue('themes');
    }

    public function retryUntil(): CarbonInterface
    {
        return now()->addHours(24);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('themes-llm')];
    }

    public function handle(LlmThemeExtractor $extractor, OffTopicOpinionRemover $remover): void
    {
        $entityName = Entity::query()->whereKey($this->entityId)->value('name');
        if (! is_string($entityName) || $this->items === []) {
            return;
        }

        $keyed = [];
        foreach ($this->items as $index => $item) {
            $keyed[] = ['key' => $item['sourceItemId'], 'text' => $item['text']];
        }

        try {
            $offTopic = $extractor->judgeRelevance($this->entityId, $entityName, $keyed);
        } catch (RequestException $e) {
            if ($e->response->status() !== 429) {
                throw $e;
            }

            $this->release(max(1, (int) $e->response->header('Retry-After') ?: 60));

            return;
        }

        $remover->remove($this->entityId, array_map('intval', $offTopic));
    }
}
