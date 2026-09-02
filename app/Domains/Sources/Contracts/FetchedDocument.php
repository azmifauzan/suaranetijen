<?php

namespace App\Domains\Sources\Contracts;

use Carbon\CarbonImmutable;

readonly class FetchedDocument
{
    public function __construct(
        public SourceDocumentRef $ref,
        public string $rawPayload,
        public string $contentType = 'text/html',
        public ?CarbonImmutable $fetchedAt = null
    ) {}
}
