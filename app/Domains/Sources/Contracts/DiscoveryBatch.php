<?php

namespace App\Domains\Sources\Contracts;

readonly class DiscoveryBatch
{
    /**
     * @param  list<SourceDocumentRef>  $documents
     */
    public function __construct(
        public array $documents,
        public ?CrawlCursor $nextCursor = null,
        public bool $hasMore = false
    ) {}
}
