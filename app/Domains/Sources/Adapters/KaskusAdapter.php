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

        $query = trim((string) ($cursor->metadata['query'] ?? $cursor->metadata['queries'][0] ?? ''));
        $listingUrl = trim((string) ($cursor->metadata['listing_url']
            ?? $cursor->metadata['category_url']
            ?? config('sources.kaskus.listing_url', '')));
        if ($listingUrl === '' && $query !== '') {
            $listingUrl = rtrim((string) config('sources.kaskus.base_url'), '/').'/search?q='.rawurlencode($query);
        }

        if ($listingUrl === '') {
            return new DiscoveryBatch([], null, false);
        }

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
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if (preg_match('~/thread/([0-9]+)~i', '/'.$path, $matches) === 1) {
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
