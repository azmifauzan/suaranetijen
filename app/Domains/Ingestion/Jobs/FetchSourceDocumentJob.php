<?php

namespace App\Domains\Ingestion\Jobs;

use App\Domains\Sources\Contracts\SourceDocumentRef;
use App\Domains\Sources\Enums\DocumentState;
use App\Domains\Sources\Exceptions\RateLimitExceededException;
use App\Domains\Sources\Models\IngestionFailure;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceDocument;
use App\Domains\Sources\Services\RawPayloadStorage;
use App\Domains\Sources\Services\SourceRateLimiter;
use App\Domains\Sources\Services\SourceRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;
use Throwable;

class FetchSourceDocumentJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    /**
     * Allow enough attempts for rate-limit backoff cycles: each self-imposed
     * throttle hit releases the job rather than failing it permanently.
     */
    public int $tries = 5;

    public function __construct(
        public SourceDocument $document
    ) {
        $this->queue = 'crawl';
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
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
        } catch (RateLimitExceededException $e) {
            $this->document->update(['state' => DocumentState::Discovered]);
            $this->release($e->retryAfterSeconds);
        } catch (Throwable $e) {
            $this->document->update(['state' => DocumentState::Failed]);
            throw $e;
        }
    }

    /**
     * Called once the job is definitively done retrying (genuine error after
     * all attempts, or rate-limit backoff exhausted its attempt budget).
     */
    public function failed(?Throwable $exception): void
    {
        IngestionFailure::record(
            $this->document->source_id,
            'fetch',
            $exception ?? new RuntimeException('Unknown fetch failure'),
            $this->document->id,
            null,
            ['canonical_url' => $this->document->canonical_url]
        );
    }
}
