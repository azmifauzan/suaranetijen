<?php

namespace App\Domains\Entities\Contracts;

interface EntityCandidateSource
{
    /**
     * Discover raw candidate terms. Callers normalize/dedupe/merge across
     * sources — a source just reports what it saw and how strongly.
     *
     * @return list<array{raw_term: string, weight: int}>
     */
    public function discover(): array;

    /**
     * Short key stored in entity_candidates.source_types, e.g. "search_query".
     */
    public function sourceType(): string;
}
