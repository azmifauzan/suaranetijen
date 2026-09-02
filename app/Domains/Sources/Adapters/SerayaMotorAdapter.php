<?php

namespace App\Domains\Sources\Adapters;

use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\DiscoveryBatch;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceDocumentRef;

class SerayaMotorAdapter extends AbstractHttpSourceAdapter
{
    protected function preflightUrl(): string
    {
        return 'https://www.serayamotor.com/diskusi/';
    }

    public function discover(CrawlCursor $cursor): DiscoveryBatch
    {
        $page = max(1, (int) ($cursor->metadata['page'] ?? 1));
        $forumIds = $cursor->metadata['forum_ids'] ?? [19, 64, 63];
        $forumId = (int) ($forumIds[0] ?? 19);
        $url = (string) ($cursor->metadata['forum_url'] ?? 'https://www.serayamotor.com/diskusi/viewforum.php?f='.$forumId);
        $response = $this->request($this->offsetUrl($url, $page));
        $response->throw();

        $documents = $this->parseHtmlDocumentLinks(
            $response->body(),
            $cursor->sourceKey,
            $url,
            '~viewtopic\\.php\\?[^#]*t=~i'
        );

        return new DiscoveryBatch(
            documents: $documents,
            nextCursor: new CrawlCursor(
                sourceKey: $cursor->sourceKey,
                cursorKey: $cursor->cursorKey,
                cursorValue: 'page_'.($page + 1),
                lastExternalId: $documents !== [] ? end($documents)->externalId : $cursor->lastExternalId,
                lastCrawledAt: now()->toImmutable(),
                metadata: ['page' => $page + 1, 'forum_ids' => $forumIds, 'forum_url' => $url]
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
                '//*[contains(concat(" ", normalize-space(@class), " "), " post ")]',
                '//article',
                '//*[contains(concat(" ", normalize-space(@class), " "), " content ")]',
                '//main',
                '//body',
            ],
            ['promo', 'iklan', 'jual', 'jualan', 'wts'],
            ['adapter' => 'serayamotor']
        );
    }
}
