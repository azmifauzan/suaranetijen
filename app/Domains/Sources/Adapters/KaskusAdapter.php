<?php

namespace App\Domains\Sources\Adapters;

use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\DiscoveryBatch;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceDocumentRef;
use App\Domains\Sources\Contracts\SourceHealth;
use Throwable;

class KaskusAdapter extends AbstractHttpSourceAdapter
{
    protected function preflightUrl(): string
    {
        return rtrim((string) config('sources.kaskus.base_url'), '/').'/';
    }

    protected function usesChallengeSolver(): bool
    {
        return true;
    }

    public function preflight(): SourceHealth
    {
        $health = parent::preflight();
        if (! $health->isOperational()) {
            return $health;
        }

        try {
            $robots = $this->request(rtrim($this->preflightUrl(), '/').'/robots.txt');

            if ($robots->status() === 401 || $robots->status() === 403) {
                return SourceHealth::policyDisabled(
                    'KASKUS robots endpoint disallows the adapter preflight request.',
                    ['http_status' => $robots->status()]
                );
            }

            if ($robots->status() === 429) {
                return SourceHealth::quotaLimited(
                    'KASKUS robots endpoint rate limited the preflight request.',
                    ['http_status' => $robots->status()]
                );
            }

            if (! $robots->successful()) {
                return SourceHealth::degraded(
                    "KASKUS robots endpoint returned HTTP status {$robots->status()}.",
                    $health->responseTimeMs,
                    ['http_status' => $robots->status()]
                );
            }

            if ($this->robotsDisallowAll($robots->body())) {
                return SourceHealth::policyDisabled(
                    'KASKUS robots.txt disallows automated access for all user agents.',
                    ['robots_status' => $robots->status()]
                );
            }

            return SourceHealth::healthy(
                'KASKUS public page and robots preflight passed successfully.',
                $health->responseTimeMs,
                ['http_status' => $health->details['http_status'] ?? null, 'robots_status' => $robots->status()]
            );
        } catch (Throwable $exception) {
            return SourceHealth::blocked(
                'KASKUS robots preflight failed: '.$exception->getMessage(),
                $health->responseTimeMs,
                ['exception' => $exception::class]
            );
        }
    }

    public function discover(CrawlCursor $cursor): DiscoveryBatch
    {
        $threadUrls = $this->stringList($cursor->metadata['thread_urls'] ?? []);
        if ($threadUrls !== []) {
            $documents = array_values(array_filter(array_map(
                fn (string $url): ?SourceDocumentRef => $this->documentRef($cursor->sourceKey, $url),
                $threadUrls
            )));

            return new DiscoveryBatch($documents, null, false);
        }

        $explicitListingUrl = trim((string) ($cursor->metadata['listing_url']
            ?? $cursor->metadata['category_url']
            ?? config('sources.kaskus.listing_url', '')));

        if ($explicitListingUrl !== '') {
            return $this->discoverExplicitListing($cursor, $explicitListingUrl);
        }

        return $this->discoverByQuery($cursor);
    }

    /**
     * Single fixed listing/subforum (e.g. a scoped Source config) — paginates
     * the same URL forever, same behaviour as LowEndTalk's category scoping.
     */
    private function discoverExplicitListing(CrawlCursor $cursor, string $listingUrl): DiscoveryBatch
    {
        $page = max(1, (int) ($cursor->metadata['page'] ?? 1));
        $response = $this->request($this->pageUrl($listingUrl, $page));
        $response->throw();
        $documents = $this->parseHtmlDocumentLinks(
            $response->body(),
            $cursor->sourceKey,
            $listingUrl,
            '~/thread/~i'
        );

        return new DiscoveryBatch(
            documents: $documents,
            nextCursor: new CrawlCursor(
                sourceKey: $cursor->sourceKey,
                cursorKey: $cursor->cursorKey,
                cursorValue: 'page_'.($page + 1),
                lastExternalId: $documents !== [] ? end($documents)->externalId : $cursor->lastExternalId,
                lastCrawledAt: now()->toImmutable(),
                metadata: [...$cursor->metadata, 'page' => $page + 1, 'listing_url' => $listingUrl]
            ),
            hasMore: $documents !== []
        );
    }

    /**
     * Site-wide search by tracked entity name/alias (auto-populated by
     * DiscoverSourceDocumentsJob). Rotates through every query instead of
     * paginating the first one forever — same fix as IndoForumAdapter's
     * forum_index rotation, for the same root cause (an index that never
     * advanced past 0).
     */
    private function discoverByQuery(CrawlCursor $cursor): DiscoveryBatch
    {
        $queries = $this->queries($cursor);

        if ($queries === []) {
            return new DiscoveryBatch([], null, false);
        }

        $queryCount = count($queries);
        $queryIndex = max(0, (int) ($cursor->metadata['query_index'] ?? 0)) % $queryCount;
        $query = $queries[$queryIndex];
        $listingUrl = rtrim((string) config('sources.kaskus.base_url'), '/').'/search?q='.rawurlencode($query);

        $page = max(1, (int) ($cursor->metadata['page'] ?? 1));
        $response = $this->request($this->pageUrl($listingUrl, $page));
        $response->throw();
        $documents = $this->parseHtmlDocumentLinks(
            $response->body(),
            $cursor->sourceKey,
            $listingUrl,
            '~/thread/~i'
        );

        // An empty results page means this query is exhausted at this cursor
        // position — move to the next tracked entity name rather than paging
        // the same search forever.
        $nextQueryIndex = $queryIndex;
        $nextPage = $page + 1;
        if ($documents === []) {
            $nextQueryIndex = ($queryIndex + 1) % $queryCount;
            $nextPage = 1;
        }

        return new DiscoveryBatch(
            documents: $documents,
            nextCursor: new CrawlCursor(
                sourceKey: $cursor->sourceKey,
                cursorKey: $cursor->cursorKey,
                cursorValue: 'query_'.$nextQueryIndex.'_page_'.$nextPage,
                lastExternalId: $documents !== [] ? end($documents)->externalId : $cursor->lastExternalId,
                lastCrawledAt: now()->toImmutable(),
                metadata: [
                    ...$cursor->metadata,
                    'queries' => $queries,
                    'query_index' => $nextQueryIndex,
                    'page' => $nextPage,
                ]
            ),
            hasMore: $documents !== []
        );
    }

    /**
     * @return list<string>
     */
    private function queries(CrawlCursor $cursor): array
    {
        if (is_string($cursor->metadata['query'] ?? null) && trim($cursor->metadata['query']) !== '') {
            return [trim($cursor->metadata['query'])];
        }

        return $this->stringList($cursor->metadata['queries'] ?? []);
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
                '//*[contains(concat(" ", normalize-space(@class), " "), " post-content ")]',
                '//*[contains(concat(" ", normalize-space(@class), " "), " post-body ")]',
                '//*[contains(concat(" ", normalize-space(@class), " "), " post ")]',
                '//article',
                '//main',
                '//body',
            ],
            ['wts', 'jual', 'dijual', 'promo', 'iklan', 'penawaran', 'offer'],
            ['adapter' => 'kaskus']
        );
    }

    protected function externalIdFromUrl(string $url): ?string
    {
        // Kaskus thread IDs used to be purely numeric; current threads use a
        // 24-character hex ObjectId (e.g. "6a979916e2919ca47207a8da"). A
        // digits-only pattern here would greedily match just the ID's leading
        // digit run (e.g. "6") and silently truncate it, colliding every
        // thread whose ID happens to start with the same digit(s) onto the
        // same source_items row. Confirmed live against
        // kaskus.co.id/komunitas/28/otomotif (6 Sep 2026).
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if (preg_match('~/thread/([0-9a-f]+)~i', '/'.$path, $matches) === 1) {
            return $matches[1];
        }

        return parent::externalIdFromUrl($url);
    }

    private function documentRef(string $sourceKey, string $url): ?SourceDocumentRef
    {
        $canonicalUrl = $this->absoluteUrl($url, $this->preflightUrl());
        if (! $this->isSameHost($canonicalUrl, $this->preflightUrl())) {
            return null;
        }

        $externalId = $this->externalIdFromUrl($canonicalUrl);
        if ($externalId === null) {
            return null;
        }

        return new SourceDocumentRef(
            sourceKey: $sourceKey,
            externalId: $externalId,
            canonicalUrl: $canonicalUrl
        );
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        return array_values(array_filter(
            is_array($value) ? array_map(static fn (mixed $item): string => is_string($item) ? trim($item) : '', $value) : [],
            static fn (string $item): bool => $item !== ''
        ));
    }

    private function robotsDisallowAll(string $payload): bool
    {
        foreach (preg_split('/\R\s*\R/u', $payload) ?: [] as $group) {
            $userAgents = [];
            $disallows = [];

            foreach (preg_split('/\R/u', $group) ?: [] as $line) {
                $line = trim((string) preg_replace('/#.*$/', '', $line));
                if ($line === '') {
                    continue;
                }

                [$directive, $value] = array_pad(explode(':', $line, 2), 2, '');
                $directive = strtolower(trim($directive));
                $value = strtolower(trim($value));

                if ($directive === 'user-agent') {
                    $userAgents[] = $value;
                } elseif ($directive === 'disallow') {
                    $disallows[] = $value;
                }
            }

            $matchesAdapter = in_array('*', $userAgents, true)
                || in_array('suaranetijen', $userAgents, true)
                || array_any($userAgents, static fn (string $userAgent): bool => str_starts_with($userAgent, 'suaranetijen/'));

            if ($matchesAdapter && in_array('/', $disallows, true)) {
                return true;
            }
        }

        return false;
    }
}
