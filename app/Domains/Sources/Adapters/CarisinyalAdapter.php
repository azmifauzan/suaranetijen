<?php

namespace App\Domains\Sources\Adapters;

use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\DiscoveryBatch;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceDocumentRef;

class CarisinyalAdapter extends AbstractHttpSourceAdapter
{
    protected function preflightUrl(): string
    {
        return rtrim((string) config('sources.carisinyal.base_url'), '/').'/';
    }

    public function discover(CrawlCursor $cursor): DiscoveryBatch
    {
        return $this->discoverWordPressFeed($cursor, (string) config('sources.carisinyal.feed_url'));
    }

    public function fetch(SourceDocumentRef $ref): FetchedDocument
    {
        return $this->fetchHttpDocument($ref);
    }

    public function extract(FetchedDocument $doc): iterable
    {
        // Carisinyal's own articles are objective tech news/specs, not
        // opinion — the real netizen sentiment lives in the native WordPress
        // reader comments (server-rendered `.comment-content` paragraphs),
        // same signal source as Kaskus/Detik's comment extraction, not the
        // article-body-as-opinion pattern used by Mojok/MediaKonsumen (whose
        // articles are themselves first-person essays/complaints).
        return $this->extractHtmlOpinions(
            $doc,
            [
                '//*[contains(concat(" ", normalize-space(@class), " "), " comment-content ")]',
            ],
            [],
            ['adapter' => 'carisinyal']
        );
    }
}
