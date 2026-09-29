<?php

namespace App\Domains\Ingestion\Jobs;

use App\Domains\Sources\Contracts\CandidateOpinion;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceDocumentRef;
use App\Domains\Sources\Enums\ProcessingState;
use App\Domains\Sources\Models\IngestionFailure;
use App\Domains\Sources\Models\RawPayload;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceDocument;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Services\RawPayloadStorage;
use App\Domains\Sources\Services\SourceRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ExtractCandidateOpinionsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SourceDocument $document,
        public string $rawPayload
    ) {
        $this->queue = 'crawl';
    }

    public function handle(SourceRegistry $registry, RawPayloadStorage $storage): void
    {
        $source = Source::find($this->document->source_id) ?? $this->document->source;
        if (! $source->enabled) {
            return;
        }

        try {
            $adapter = $registry->resolve($source);

            $ref = new SourceDocumentRef(
                sourceKey: $source->key,
                externalId: $this->document->external_id,
                canonicalUrl: $this->document->canonical_url,
                title: $this->document->title,
                publishedAt: $this->document->published_at
            );

            $fetchedDoc = new FetchedDocument(
                ref: $ref,
                rawPayload: $this->rawPayload
            );

            $this->storeOpinions($source, [...$adapter->extract($fetchedDoc)]);
        } catch (Throwable $e) {
            IngestionFailure::record($source->id, 'extract', $e, $this->document->id);
            throw $e;
        }
    }

    /**
     * Persist every opinion of the document with a fixed number of queries. Workers reach the
     * database over the network, so a per-opinion updateOrCreate/exists/insert/update loop cost
     * ~5 round trips each and pushed large pages (100s of YouTube comments) past the job timeout.
     *
     * @param  array<int, CandidateOpinion>  $opinions
     */
    private function storeOpinions(Source $source, array $opinions): void
    {
        if ($opinions === []) {
            return;
        }

        $now = now();
        $ttlHours = (int) ($source->retention_policy['raw_ttl_hours'] ?? RawPayloadStorage::DEFAULT_TTL_HOURS);
        $expiresAt = $now->copy()->addHours($ttlHours);

        $rows = [];
        $textsByExternalId = [];
        foreach ($opinions as $opinion) {
            $rows[$opinion->externalItemId] = [
                'source_id' => $source->id,
                'external_id' => $opinion->externalItemId,
                'source_document_id' => $this->document->id,
                'content_hash' => $opinion->getContentHash(),
                'processing_state' => ProcessingState::Pending->value,
                'published_at' => $opinion->publishedAt,
            ];
            $textsByExternalId[$opinion->externalItemId] = $opinion->text;
        }

        // One transaction: a worker killed mid-way (timeout, deploy) must not leave items
        // without a payload, which no later step would ever pick up.
        $itemIds = DB::transaction(function () use ($rows, $source, $textsByExternalId, $now, $expiresAt): array {
            foreach (array_chunk(array_values($rows), 200) as $chunk) {
                SourceItem::query()->upsert(
                    $chunk,
                    ['source_id', 'external_id'],
                    ['source_document_id', 'content_hash', 'processing_state', 'published_at']
                );
            }

            $itemIds = [];
            foreach (array_chunk(array_keys($rows), 500) as $externalIds) {
                $itemIds += SourceItem::query()
                    ->where('source_id', $source->id)
                    ->whereIn('external_id', $externalIds)
                    ->pluck('id', 'external_id')
                    ->all();
            }

            $alreadyStored = [];
            foreach (array_chunk(array_values($itemIds), 500) as $ids) {
                foreach (RawPayload::query()->whereIn('source_item_id', $ids)->pluck('source_item_id') as $id) {
                    $alreadyStored[$id] = true;
                }
            }

            $payloadRows = [];
            $refsByItemId = [];
            foreach ($itemIds as $externalId => $itemId) {
                if (isset($alreadyStored[$itemId])) {
                    continue;
                }

                $ref = 'payload-'.Str::uuid()->toString();
                $refsByItemId[$itemId] = $ref;
                $payloadRows[] = [
                    'source_id' => $source->id,
                    'source_item_id' => $itemId,
                    'payload_ref' => $ref,
                    'payload' => $textsByExternalId[$externalId],
                    'content_type' => 'text/plain',
                    'expires_at' => $expiresAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($payloadRows, 100) as $chunk) {
                RawPayload::query()->insert($chunk);
            }

            foreach (array_chunk($refsByItemId, 200, true) as $chunk) {
                $cases = str_repeat('when ? then ? ', count($chunk));
                $bindings = [];
                foreach ($chunk as $itemId => $ref) {
                    array_push($bindings, $itemId, $ref);
                }
                array_push($bindings, $expiresAt, $now, ...array_keys($chunk));

                DB::update(
                    "update source_items set raw_payload_ref = case id {$cases}end, expires_at = ?, updated_at = ? where id in (".implode(',', array_fill(0, count($chunk), '?')).')',
                    $bindings
                );
            }

            return $itemIds;
        });

        foreach ($itemIds as $itemId) {
            MatchEntitiesJob::dispatch($itemId);
        }
    }
}
