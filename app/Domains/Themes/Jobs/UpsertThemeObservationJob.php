<?php

namespace App\Domains\Themes\Jobs;

use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Themes\Models\ThemeObservation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpsertThemeObservationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $entityId,
        public int $themeId,
        public int $sourceId,
        public ?int $sourceItemId,
        public ?string $sourceDocumentHash,
        public SentimentClass $sentiment,
        public ?float $confidence = null,
        public ?CarbonInterface $publishedAt = null
    ) {
        $this->onQueue('analysis');
    }

    public function handle(): ThemeObservation
    {
        // Enforce deduplication: same entity, theme, and source item never duplicates
        if ($this->sourceItemId !== null) {
            $observation = ThemeObservation::query()->updateOrCreate(
                [
                    'entity_id' => $this->entityId,
                    'theme_id' => $this->themeId,
                    'source_item_id' => $this->sourceItemId,
                ],
                [
                    'source_id' => $this->sourceId,
                    'source_document_hash' => $this->sourceDocumentHash,
                    'sentiment' => $this->sentiment,
                    'confidence' => $this->confidence,
                    'published_at' => $this->publishedAt,
                ]
            );
        } else {
            $observation = ThemeObservation::query()->create([
                'entity_id' => $this->entityId,
                'theme_id' => $this->themeId,
                'source_id' => $this->sourceId,
                'source_item_id' => null,
                'source_document_hash' => $this->sourceDocumentHash,
                'sentiment' => $this->sentiment,
                'confidence' => $this->confidence,
                'published_at' => $this->publishedAt,
            ]);
        }

        $observedAt = $this->publishedAt ?? CarbonImmutable::now();
        AggregateDailyThemeJob::dispatch($this->entityId, $observedAt);
        RefreshThemeSnapshotJob::dispatch($this->entityId);

        return $observation;
    }
}
