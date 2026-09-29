<?php

namespace App\Domains\Sources\Adapters;

use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\DiscoveryBatch;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceDocumentRef;

class MojokAdapter extends AbstractHttpSourceAdapter
{
    protected function preflightUrl(): string
    {
        return rtrim((string) config('sources.mojok.base_url'), '/').'/';
    }

    public function discover(CrawlCursor $cursor): DiscoveryBatch
    {
        return $this->discoverWordPressFeed($cursor, (string) config('sources.mojok.feed_url'));
    }

    public function fetch(SourceDocumentRef $ref): FetchedDocument
    {
        return $this->fetchHttpDocument($ref);
    }

    public function extract(FetchedDocument $doc): iterable
    {
        return $this->extractHtmlOpinions(
            $doc,
            [
                '//*[contains(concat(" ", normalize-space(@class), " "), " post-body-content ")]',
                '//article',
                '//main',
                '//body',
            ],
            [],
            ['adapter' => 'mojok']
        );
    }
}
