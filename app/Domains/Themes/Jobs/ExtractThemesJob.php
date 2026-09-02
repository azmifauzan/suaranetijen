<?php

namespace App\Domains\Themes\Jobs;

use App\Domains\Sentiment\Enums\SentimentClass;
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

    public function handle(ThemeExtractor $extractor): void
    {
        $extracted = $extractor->extract($this->text, $this->contextSentiment);

        foreach ($extracted as $item) {
            UpsertThemeObservationJob::dispatch(
                entityId: $this->entityId,
                themeId: $item['theme']->id,
                sourceId: $this->sourceId,
                sourceItemId: $this->sourceItemId,
                sourceDocumentHash: $this->sourceDocumentHash,
                sentiment: $item['sentiment'],
                confidence: $item['confidence'],
                publishedAt: $this->publishedAt
            );
        }
    }
}
