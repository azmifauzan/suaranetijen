<?php

namespace App\Domains\Ingestion\Jobs;

use App\Domains\Entities\Models\Entity;
use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Enums\DocumentState;
use App\Domains\Sources\Exceptions\RateLimitExceededException;
use App\Domains\Sources\Models\CrawlState;
use App\Domains\Sources\Models\IngestionFailure;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceDocument;
use App\Domains\Sources\Services\SourceRateLimiter;
use App\Domains\Sources\Services\SourceRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;
use Throwable;

class DiscoverSourceDocumentsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    /**
     * Genuine-error retry budget. Rate-limit hits are handled separately (see
     * $rateLimitBounces) so competing fetch jobs on the same source can't
     * exhaust this budget before discovery ever gets its turn.
     */
    public int $tries = 5;

    /**
     * Horizon's supervisor-crawl config caps job execution at 60s. A source
     * routed through FlareSolverr (see AbstractHttpSourceAdapter::request())
     * can legitimately take close to its own maxTimeout (45s by default) just
     * to solve a challenge, before any network/processing overhead — without
     * headroom here, the job gets killed before FlareSolverr even responds.
     */
    public int $timeout = 90;

    /**
     * Rate-limit bounces before giving up and recording a permanent failure.
     */
    private const MAX_RATE_LIMIT_BOUNCES = 30;

    public function __construct(
        public Source $source,
        public string $cursorKey = 'default',
        public int $rateLimitBounces = 0
    ) {
        $this->queue = 'discovery';
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(SourceRegistry $registry, SourceRateLimiter $rateLimiter): void
    {
        $source = Source::find($this->source->id) ?? $this->source;

        if (! $source->enabled || ! $source->health_state->isOperational()) {
            return;
        }

        try {

            $crawlState = CrawlState::firstOrCreate(
                ['source_id' => $this->source->id, 'cursor_key' => $this->cursorKey],
                [
                    'cursor_value' => null,
                    'last_external_id' => null,
                    'metadata' => [],
                ]
            );

            $cursorMetadata = array_merge(
                $this->source->crawl_policy ?? [],
                $crawlState->metadata ?? []
            );

            if (in_array($this->source->key, ['bluesky', 'youtube', 'kaskus'], true)) {
                $trackedTerms = Entity::query()
                    ->active()
                    ->searchable()
                    ->with('aliases')
                    ->get()
                    ->flatMap(function (Entity $entity): array {
                        return [
                            $entity->name,
                            ...$entity->aliases->pluck('normalized_alias')->all(),
                        ];
                    })
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if ($this->source->key === 'bluesky') {
                    $cursorMetadata['aliases'] = $trackedTerms;
                } elseif (empty($cursorMetadata['queries'])) {
                    $cursorMetadata['queries'] = $trackedTerms;
                }
            }

            $cursor = new CrawlCursor(
                sourceKey: $this->source->key,
                cursorKey: $this->cursorKey,
                cursorValue: $crawlState->cursor_value,
                lastExternalId: $crawlState->last_external_id,
                lastCrawledAt: $crawlState->last_crawled_at,
                metadata: $cursorMetadata
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
        } catch (RateLimitExceededException $e) {
            if ($this->rateLimitBounces >= self::MAX_RATE_LIMIT_BOUNCES) {
                IngestionFailure::record(
                    $source->id,
                    'discovery',
                    $e,
                    null,
                    null,
                    ['rate_limit_bounces_exhausted' => true]
                );

                return;
            }

            // Requeue as a fresh job rather than release(): this keeps rate-limit
            // backoff off the genuine-error retry budget ($tries) entirely.
            $this->delete();
            self::dispatch($this->source, $this->cursorKey, $this->rateLimitBounces + 1)
                ->delay(now()->addSeconds($e->retryAfterSeconds));
        } catch (Throwable $e) {
            throw $e;
        }
    }

    /**
     * Called once a genuine error exhausts its retry budget.
     */
    public function failed(?Throwable $exception): void
    {
        IngestionFailure::record(
            $this->source->id,
            'discovery',
            $exception ?? new RuntimeException('Unknown discovery failure')
        );
    }
}
