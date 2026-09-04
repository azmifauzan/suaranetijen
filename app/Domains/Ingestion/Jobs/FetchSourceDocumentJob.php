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
     * Genuine-error retry budget. Rate-limit hits are handled separately (see
     * $rateLimitBounces) so a large discovery batch queued against a low
     * rate limit doesn't exhaust this budget before its turn ever comes up.
     */
    public int $tries = 5;

    /**
     * Rate-limit bounces before giving up and recording a permanent failure.
     * Deliberately generous: a large discovery batch against a low per-source
     * rate limit can need many cycles before every document gets its turn.
     */
    private const MAX_RATE_LIMIT_BOUNCES = 30;

    public function __construct(
        public SourceDocument $document,
        public int $rateLimitBounces = 0
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

            if ($this->rateLimitBounces >= self::MAX_RATE_LIMIT_BOUNCES) {
                $this->document->update(['state' => DocumentState::Failed]);
                IngestionFailure::record(
                    $source->id,
                    'fetch',
                    $e,
                    $this->document->id,
                    null,
                    ['canonical_url' => $this->document->canonical_url, 'rate_limit_bounces_exhausted' => true]
                );

                return;
            }

            // Requeue as a fresh job rather than release(): this keeps rate-limit
            // backoff off the genuine-error retry budget ($tries) entirely, so a
            // large discovery batch against a low rate limit can't exhaust it.
            $this->delete();
            self::dispatch($this->document, $this->rateLimitBounces + 1)
                ->delay(now()->addSeconds($e->retryAfterSeconds));
        } catch (Throwable $e) {
            $this->document->update(['state' => DocumentState::Failed]);
            throw $e;
        }
    }

    /**
     * Called once a genuine error exhausts its retry budget.
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
