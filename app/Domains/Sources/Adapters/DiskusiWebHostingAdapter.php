<?php

namespace App\Domains\Sources\Adapters;

use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\DiscoveryBatch;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceDocumentRef;

class DiskusiWebHostingAdapter extends AbstractHttpSourceAdapter
{
    protected function preflightUrl(): string
    {
        return 'https://www.diskusiwebhosting.com/';
    }

    public function discover(CrawlCursor $cursor): DiscoveryBatch
    {
        $page = max(1, (int) ($cursor->metadata['page'] ?? 1));
        $feedUrl = (string) ($cursor->metadata['rss_url'] ?? 'https://www.diskusiwebhosting.com/index.php?forums/-/index.rss');
        $response = $this->request($this->pageUrl($feedUrl, $page));
        $response->throw();

        $documents = $this->parseFeedDocuments($response->body(), $cursor->sourceKey);
        if ($documents === []) {
            $documents = $this->parseHtmlDocumentLinks(
                $response->body(),
                $cursor->sourceKey,
                $feedUrl,
                '~(?:/threads/|/thread/)~i'
            );
        }

        return new DiscoveryBatch(
            documents: $documents,
            nextCursor: new CrawlCursor(
                sourceKey: $cursor->sourceKey,
                cursorKey: $cursor->cursorKey,
                cursorValue: 'page_'.($page + 1),
                lastExternalId: $documents !== [] ? end($documents)->externalId : $cursor->lastExternalId,
                lastCrawledAt: now()->toImmutable(),
                metadata: ['page' => $page + 1, 'rss_url' => $feedUrl]
            ),
            hasMore: $documents !== []
        );
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
                '//article',
                '//*[contains(concat(" ", normalize-space(@class), " "), " message ")]',
                '//*[contains(concat(" ", normalize-space(@class), " "), " post ")]',
                '//main',
                '//body',
            ],
            ['wts', 'jual', 'dijual', 'promo', 'iklan', 'penawaran', 'offer', 'sale'],
            ['adapter' => 'diskusiwebhosting']
        );
    }
}
