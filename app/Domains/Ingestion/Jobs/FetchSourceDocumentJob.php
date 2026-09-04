<?php

namespace App\Domains\Ingestion\Jobs;

use App\Domains\Sources\Contracts\SourceDocumentRef;
use App\Domains\Sources\Enums\DocumentState;
use App\Domains\Sources\Models\IngestionFailure;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceDocument;
use App\Domains\Sources\Services\RawPayloadStorage;
use App\Domains\Sources\Services\SourceRateLimiter;
use App\Domains\Sources\Services\SourceRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class FetchSourceDocumentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SourceDocument $document
    ) {
        $this->queue = 'crawl';
    }

    public function handle(
        SourceRegistry $registry,
        SourceRateLimiter $rateLimiter,
        RawPayloadStorage $storage
    ): void {
        $source = Source::find($this->document->source_id) ?? $this->document->source;

        if (! $source->enabled || ! $source->health_state->isOperational()) {
            return;
        }

        $this->document->update(['state' => DocumentState::Fetching]);

        try {
            $adapter = $registry->resolve($source);

            $ref = new SourceDocumentRef(
                sourceKey: $source->key,
                externalId: $this->document->external_id,
                canonicalUrl: $this->document->canonical_url,
                title: $this->document->title,
                publishedAt: $this->document->published_at
            );

            $fetchedDoc = $rateLimiter->attempt($source, fn () => $adapter->fetch($ref));

            // Store raw payload with temporary TTL
            $rawPayload = $storage->store($source, $fetchedDoc->rawPayload, null, $fetchedDoc->contentType);

            $this->document->update([
                'state' => DocumentState::Fetched,
                'content_hash' => hash('sha256', $fetchedDoc->rawPayload),
            ]);

            ExtractCandidateOpinionsJob::dispatch($this->document, $rawPayload->payload);
        } catch (Throwable $e) {
            $this->document->update(['state' => DocumentState::Failed]);
            IngestionFailure::record(
                $source->id,
                'fetch',
                $e,
                $this->document->id,
                null,
                ['canonical_url' => $this->document->canonical_url]
            );
            throw $e;
        }
    }
}
