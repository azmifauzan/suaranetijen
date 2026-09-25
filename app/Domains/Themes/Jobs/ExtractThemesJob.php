<?php

namespace App\Domains\Themes\Jobs;

use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Themes\Services\LlmThemeExtractor;
use App\Domains\Themes\Services\ThemeExtractor;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExtractThemesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
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
        $this->onQueue('analysis');
    }

    public function handle(ThemeExtractor $keywordExtractor, LlmThemeExtractor $llmExtractor): void
    {
        $useLlm = config('themes.extractor') === 'llm';

        if ($useLlm) {
            $entityName = Entity::query()->whereKey($this->entityId)->value('name');
            if (! is_string($entityName)) {
                return;
            }

            $extracted = $llmExtractor->extract($this->entityId, $entityName, $this->text);
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
