<?php

namespace App\Domains\Themes\Jobs;

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Themes\Services\LlmThemeExtractor;
use App\Domains\Themes\Services\OffTopicOpinionRemover;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

/**
 * Batched sibling of ExtractThemesJob: one LLM call covers many opinions from the
 * same entity (themes:backfill-batch), instead of one call per opinion. Shares the
 * same themes-llm rate limiter and 429/retry handling as the single-opinion job.
 */
class ExtractThemesBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Rate-limit releases and 429s must not eat the retry budget — see ExtractThemesJob.
     */
    public int $maxExceptions = 3;

    /**
     * @param  list<array{sourceItemId: int, sourceId: int, sourceDocumentHash: string|null, publishedAt: string|null, text: string}>  $items
     */
    public function __construct(
        public int $entityId,
        public array $items,
    ) {
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

    public function handle(LlmThemeExtractor $extractor): void
    {
        $entityName = Entity::query()->whereKey($this->entityId)->value('name');
        if (! is_string($entityName) || $this->items === []) {
            return;
        }

        $keyedItems = [];
        foreach ($this->items as $index => $item) {
            $keyedItems[$index] = ['key' => $index, 'text' => $item['text']];
        }

        $offTopic = [];

        try {
            $results = $extractor->extractBatch($this->entityId, $entityName, $keyedItems, $offTopic);
        } catch (RequestException $e) {
            if ($e->response->status() !== 429) {
                throw $e;
            }

            $this->release(max(1, (int) $e->response->header('Retry-After') ?: 60));

            return;
        }

        $offTopicIds = array_map(fn ($index) => $this->items[(int) $index]['sourceItemId'], $offTopic);

        foreach ($results as $index => $themes) {
            $item = $this->items[(int) $index];

            foreach ($themes as $theme) {
                UpsertThemeObservationJob::dispatch(
                    entityId: $this->entityId,
                    themeId: $theme['theme']->id,
                    sourceId: $item['sourceId'],
                    sourceItemId: $item['sourceItemId'],
                    sourceDocumentHash: $item['sourceDocumentHash'],
                    sentiment: $theme['sentiment'],
                    confidence: $theme['confidence'],
                    publishedAt: $item['publishedAt'] !== null ? CarbonImmutable::parse($item['publishedAt']) : null,
                    extractor: 'llm',
                    context: $theme['context'],
                );
            }
        }

        app(OffTopicOpinionRemover::class)->remove($this->entityId, $offTopicIds);

        // Opinions with zero themes leave no theme_observations row, so mark them here
        // or themes:extract-pending would resend them every run.
        SentimentObservation::query()
            ->where('entity_id', $this->entityId)
            ->whereIn('source_item_id', array_column($this->items, 'sourceItemId'))
            ->update(['themes_extracted_at' => now()]);
    }
}
