<?php

namespace App\Domains\Sources\Contracts;

use Carbon\CarbonImmutable;

readonly class SourceDocumentRef
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $sourceKey,
        public string $externalId,
        public ?string $canonicalUrl = null,
        public ?string $title = null,
        public ?CarbonImmutable $publishedAt = null,
        public array $metadata = []
    ) {}
}
