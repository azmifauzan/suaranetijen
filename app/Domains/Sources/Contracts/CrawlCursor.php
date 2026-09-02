<?php

namespace App\Domains\Sources\Contracts;

use Carbon\CarbonImmutable;

readonly class CrawlCursor
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $sourceKey,
        public string $cursorKey = 'default',
        public ?string $cursorValue = null,
        public ?string $lastExternalId = null,
        public ?CarbonImmutable $lastCrawledAt = null,
        public array $metadata = []
    ) {}

    public static function initial(string $sourceKey, string $cursorKey = 'default'): self
    {
        return new self(
            sourceKey: $sourceKey,
            cursorKey: $cursorKey,
            cursorValue: null,
            lastExternalId: null,
            lastCrawledAt: null,
            metadata: []
        );
    }
}
