<?php

namespace App\Domains\Ingestion\Jobs;

use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceDocumentRef;
use App\Domains\Sources\Enums\ProcessingState;
use App\Domains\Sources\Models\RawPayload;
use App\Domains\Sources\Models\SourceDocument;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Services\RawPayloadStorage;
use App\Domains\Sources\Services\SourceRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
        $source = $this->document->source;
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

        $opinions = $adapter->extract($fetchedDoc);

        foreach ($opinions as $opinion) {
            $item = SourceItem::updateOrCreate(
                [
                    'source_id' => $source->id,
                    'external_id' => $opinion->externalItemId,
                ],
                [
                    'source_document_id' => $this->document->id,
                    'content_hash' => $opinion->getContentHash(),
                    'processing_state' => ProcessingState::Pending,
                    'published_at' => $opinion->publishedAt,
                ]
            );

            if (! RawPayload::query()->where('source_item_id', $item->id)->exists()) {
                $storage->store($source, $opinion->text, $item, 'text/plain');
            }

            MatchEntitiesJob::dispatch($item->id);
        }
    }
}
