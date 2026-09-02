<?php

namespace App\Domains\Sources\Contracts;

use Carbon\CarbonImmutable;

readonly class CandidateOpinion
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $sourceKey,
        public string $externalItemId,
        public ?string $externalDocumentId = null,
        public ?string $canonicalUrl = null,
        public ?CarbonImmutable $publishedAt = null,
        public string $text = '',
        public ?string $contentHash = null,
        public array $metadata = []
    ) {}

    /**
     * Get or compute the SHA-256 hash of the cleaned text.
     */
    public function getContentHash(): string
    {
        return $this->contentHash ?? hash('sha256', trim($this->text));
    }
}
