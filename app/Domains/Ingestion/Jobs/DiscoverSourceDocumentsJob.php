<?php

namespace App\Domains\Ingestion\Jobs;

use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Enums\DocumentState;
use App\Domains\Sources\Models\CrawlState;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceDocument;
use App\Domains\Sources\Services\SourceRateLimiter;
use App\Domains\Sources\Services\SourceRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DiscoverSourceDocumentsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Source $source,
        public string $cursorKey = 'default'
    ) {
        $this->queue = 'discovery';
    }

    public function handle(SourceRegistry $registry, SourceRateLimiter $rateLimiter): void
    {
        if (! $this->source->enabled || ! $this->source->health_state->isOperational()) {
            return;
        }

        $crawlState = CrawlState::firstOrCreate(
            ['source_id' => $this->source->id, 'cursor_key' => $this->cursorKey],
            [
                'cursor_value' => null,
                'last_external_id' => null,
                'metadata' => [],
            ]
        );

        $cursor = new CrawlCursor(
            sourceKey: $this->source->key,
            cursorKey: $this->cursorKey,
            cursorValue: $crawlState->cursor_value,
            lastExternalId: $crawlState->last_external_id,
            lastCrawledAt: $crawlState->last_crawled_at,
            metadata: $crawlState->metadata ?? []
        );

        $adapter = $registry->resolve($this->source);

        $batch = $rateLimiter->attempt($this->source, fn () => $adapter->discover($cursor));

        foreach ($batch->documents as $docRef) {
            $doc = SourceDocument::updateOrCreate(
                [
                    'source_id' => $this->source->id,
                    'external_id' => $docRef->externalId,
                ],
                [
                    'canonical_url' => $docRef->canonicalUrl,
                    'title' => $docRef->title,
                    'title_hash' => $docRef->title !== null ? hash('sha256', $docRef->title) : null,
                    'state' => DocumentState::Discovered,
                    'published_at' => $docRef->publishedAt,
                    'last_seen_at' => CarbonImmutable::now(),
                ]
            );

            FetchSourceDocumentJob::dispatch($doc);
        }

        if ($batch->nextCursor !== null) {
            $crawlState->update([
                'cursor_value' => $batch->nextCursor->cursorValue,
                'last_external_id' => $batch->nextCursor->lastExternalId,
                'last_crawled_at' => CarbonImmutable::now(),
                'metadata' => $batch->nextCursor->metadata,
            ]);
        }
    }
}
