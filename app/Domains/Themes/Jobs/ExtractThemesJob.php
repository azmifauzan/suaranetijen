<?php

namespace App\Domains\Themes\Jobs;

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Themes\Models\ThemeObservation;
use App\Domains\Themes\Services\LlmThemeExtractor;
use App\Domains\Themes\Services\ThemeExtractor;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

class ExtractThemesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Rate-limit releases and 429s must not eat the retry budget (same lesson as the
     * crawler's self-throttle): only real exceptions count, capped by $maxExceptions,
     * while retryUntil() bounds how long a throttled job may keep waiting.
     */
    public int $maxExceptions = 3;

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
        return config('themes.extractor') === 'llm' ? [new RateLimited('themes-llm')] : [];
    }

    public function __construct(
        public int $entityId,
        public int $sourceId,
        public ?int $sourceItemId,
        public string $text,
        public ?string $sourceDocumentHash = null,
        public ?SentimentClass $contextSentiment = null,
        public ?CarbonInterface $publishedAt = null
    ) {
        $this->onQueue('themes');
    }

    private function alreadyExtractedByLlm(): bool
    {
        return $this->sourceItemId !== null
            && ThemeObservation::query()
                ->where('source_item_id', $this->sourceItemId)
                ->where('extractor', 'llm')
                ->exists();
    }

    public function handle(ThemeExtractor $keywordExtractor, LlmThemeExtractor $llmExtractor): void
    {
        $useLlm = config('themes.extractor') === 'llm';

        if ($useLlm) {
            // Short comments ("mantap") carry no concrete judgement; skipping them
            // saves an LLM call. An item that already has LLM observations is skipped
            // so backfills, replays and retries never double-count or re-bill it.
            if (mb_strlen(trim($this->text)) < (int) config('themes.llm_min_chars', 30)
                || $this->alreadyExtractedByLlm()) {
                return;
            }

            $entityName = Entity::query()->whereKey($this->entityId)->value('name');
            if (! is_string($entityName)) {
                return;
            }

            try {
                $extracted = $llmExtractor->extract($this->entityId, $entityName, $this->text);
            } catch (RequestException $e) {
                if ($e->response->status() !== 429) {
                    throw $e;
                }

                $this->release(max(1, (int) $e->response->header('Retry-After') ?: 60));

                return;
            }
        } else {
            $extracted = array_map(
                fn (array $item) => [...$item, 'context' => null],
                $keywordExtractor->extract($this->text, $this->contextSentiment)
            );
        }

        foreach ($extracted as $item) {
            UpsertThemeObservationJob::dispatch(
                entityId: $this->entityId,
                themeId: $item['theme']->id,
                sourceId: $this->sourceId,
                sourceItemId: $this->sourceItemId,
                sourceDocumentHash: $this->sourceDocumentHash,
                sentiment: $item['sentiment'],
                confidence: $item['confidence'],
                publishedAt: $this->publishedAt,
                extractor: $useLlm ? 'llm' : 'keyword',
                context: $item['context'],
            );
        }
    }
}
