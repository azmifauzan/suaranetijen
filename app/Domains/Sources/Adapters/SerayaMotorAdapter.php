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

    protected function usesChallengeSolver(): bool
    {
        return true;
    }

    public function discover(CrawlCursor $cursor): DiscoveryBatch
    {
        $forumIds = $cursor->metadata['forum_ids'] ?? [19, 64, 63];
        $forumCount = count($forumIds);
        $forumIndex = max(0, (int) ($cursor->metadata['forum_index'] ?? 0)) % $forumCount;
        $forumId = (int) ($forumIds[$forumIndex] ?? 19);
        $page = max(1, (int) ($cursor->metadata['page'] ?? 1));
        $url = 'https://www.serayamotor.com/diskusi/viewforum.php?f='.$forumId;
        $response = $this->request($this->offsetUrl($url, $page));
        $response->throw();

        $documents = $this->parseHtmlDocumentLinks(
            $response->body(),
            $cursor->sourceKey,
            $url,
            '~viewtopic\\.php\\?[^#]*t=~i'
        );

        // An empty page means this forum is exhausted at this cursor position
        // (or currently unparseable) — move to the next configured forum
        // rather than paginating the same one forever.
        $nextForumIndex = $forumIndex;
        $nextPage = $page + 1;
        if ($documents === []) {
            $nextForumIndex = ($forumIndex + 1) % $forumCount;
            $nextPage = 1;
        }

        return new DiscoveryBatch(
            documents: $documents,
            nextCursor: new CrawlCursor(
                sourceKey: $cursor->sourceKey,
                cursorKey: $cursor->cursorKey,
                cursorValue: 'forum_'.$forumIds[$nextForumIndex].'_page_'.$nextPage,
                lastExternalId: $documents !== [] ? end($documents)->externalId : $cursor->lastExternalId,
                lastCrawledAt: now()->toImmutable(),
                metadata: [
                    ...$cursor->metadata,
                    'forum_ids' => $forumIds,
                    'forum_index' => $nextForumIndex,
                    'page' => $nextPage,
                ]
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
