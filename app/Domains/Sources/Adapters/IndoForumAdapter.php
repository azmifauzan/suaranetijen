<?php

namespace App\Domains\Sources\Adapters;

use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\DiscoveryBatch;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceDocumentRef;

class IndoForumAdapter extends AbstractHttpSourceAdapter
{
    /**
     * @var array<int, string>
     */
    private const FORUM_PATHS = [
        139 => 'forum-komplain.139',
        107 => 'info-terbaru-reviews.107',
        93 => 'computer-stuff.93',
        104 => '104',
    ];

    protected function preflightUrl(): string
    {
        return 'https://www.forum.or.id/';
    }

    public function discover(CrawlCursor $cursor): DiscoveryBatch
    {
        $forumIds = array_values(array_filter(
            $cursor->metadata['forum_ids'] ?? [],
            fn (mixed $forumId): bool => (is_int($forumId) || ctype_digit((string) $forumId))
                && array_key_exists((int) $forumId, self::FORUM_PATHS)
        ));

        if ($forumIds === []) {
            return new DiscoveryBatch([], null, false);
        }

        $page = max(1, (int) ($cursor->metadata['page'] ?? 1));
        $forumId = (int) ($forumIds[0]);
        $url = (string) ($cursor->metadata['forum_url'] ?? $this->forumUrl($forumId));
        $response = $this->offsetUrl($url, $page);
        if (str_contains($url, '/forums/')) {
            $response = $this->pageUrl($url, $page);
        }
        $response = $this->request($response);
        $response->throw();

        $documents = $this->parseHtmlDocumentLinks(
            $response->body(),
            $cursor->sourceKey,
            $url,
            '~(?:viewtopic\\.php\\?[^#]*f='.preg_quote((string) $forumId, '~').'[^#]*t=|/threads/[^/#?]+\\.[0-9]+(?:/|$))~i'
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
            ['promo', 'iklan', 'jualan', 'wts'],
            ['adapter' => 'indoforum']
        );
    }

    private function forumUrl(int $forumId): string
    {
        return 'https://www.forum.or.id/forums/'.self::FORUM_PATHS[$forumId].'/';
    }
}
