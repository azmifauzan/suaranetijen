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
        $page = max(1, (int) ($cursor->metadata['page'] ?? 1));
        $feedUrl = (string) ($cursor->metadata['feed_url'] ?? config('sources.mojok.feed_url'));
        // WordPress feed pagination uses ?paged=N, not the ?page=N that
        // AbstractHttpSourceAdapter::pageUrl() builds for forum-style
        // pagination — page= is silently ignored on a feed endpoint (same
        // quirk as MediaKonsumenAdapter).
        $url = $page > 1
            ? $feedUrl.(str_contains($feedUrl, '?') ? '&' : '?').'paged='.$page
            : $feedUrl;
        $response = $this->request($url);
        $response->throw();

        $documents = $this->parseFeedDocuments($response->body(), $cursor->sourceKey);

        return new DiscoveryBatch(
            documents: $documents,
            nextCursor: new CrawlCursor(
                sourceKey: $cursor->sourceKey,
                cursorKey: $cursor->cursorKey,
                cursorValue: 'page_'.($page + 1),
                lastExternalId: $documents !== [] ? end($documents)->externalId : $cursor->lastExternalId,
                lastCrawledAt: now()->toImmutable(),
                metadata: ['page' => $page + 1, 'feed_url' => $feedUrl]
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
