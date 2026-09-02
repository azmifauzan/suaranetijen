<?php

namespace App\Domains\Sources\Contracts;

interface SourceAdapter
{
    /**
     * Perform preflight check against the source to evaluate availability and compliance.
     */
    public function preflight(): SourceHealth;

    /**
     * Discover documents (threads, videos, posts) incrementally from the source cursor.
     */
    public function discover(CrawlCursor $cursor): DiscoveryBatch;

    /**
     * Fetch document raw payload by its reference.
     */
    public function fetch(SourceDocumentRef $ref): FetchedDocument;

    /**
     * Extract candidate opinions from the fetched document payload.
     *
     * @return iterable<CandidateOpinion>
     */
    public function extract(FetchedDocument $doc): iterable;
}
