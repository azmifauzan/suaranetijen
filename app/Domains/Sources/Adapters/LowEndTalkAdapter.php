<?php

namespace App\Domains\Sources\Adapters;

use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\DiscoveryBatch;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceDocumentRef;

class LowEndTalkAdapter extends AbstractHttpSourceAdapter
{
    /**
     * @var list<string>
     */
    private const ALLOWED_CATEGORIES = ['reviews', 'providers', 'outages'];

    protected function preflightUrl(): string
    {
        return rtrim((string) config('sources.lowendtalk.base_url'), '/').'/';
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

        $categoryUrls = $this->stringList(
            $cursor->metadata['category_urls']
            ?? ($cursor->metadata['category_url'] ?? config('sources.lowendtalk.category_urls', []))
        );
        $categoryIndex = max(0, (int) ($cursor->metadata['category_index'] ?? 0));
        $categoryUrl = $categoryUrls[$categoryIndex] ?? null;
        if (! is_string($categoryUrl) || ! $this->isAllowedCategoryUrl($categoryUrl)) {
            return new DiscoveryBatch([], null, false);
        }

        $page = max(1, (int) ($cursor->metadata['page'] ?? 1));
        $response = $this->request($this->pageUrl($categoryUrl, $page));
        $response->throw();
        $documents = $this->parseHtmlDocumentLinks(
            $response->body(),
            $cursor->sourceKey,
            $categoryUrl,
            '~/(?:discussion|topic)/~i'
        );

        return new DiscoveryBatch(
            documents: $documents,
            nextCursor: new CrawlCursor(
                sourceKey: $cursor->sourceKey,
                cursorKey: $cursor->cursorKey,
                cursorValue: 'page_'.($page + 1),
                lastExternalId: $documents !== [] ? end($documents)->externalId : $cursor->lastExternalId,
                lastCrawledAt: now()->toImmutable(),
                metadata: [
                    ...$cursor->metadata,
                    'category_urls' => $categoryUrls,
                    'category_index' => $categoryIndex,
                    'category_url' => $categoryUrl,
                    'page' => $page + 1,
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
                '//*[contains(concat(" ", normalize-space(@class), " "), " Message ")]',
                '//*[contains(concat(" ", normalize-space(@class), " "), " Comment ")]',
                '//*[contains(concat(" ", normalize-space(@class), " "), " post-body ")]',
                '//*[contains(concat(" ", normalize-space(@class), " "), " post ")]',
                '//article',
                '//main',
                '//body',
            ],
            ['offer', 'offers', 'promo', 'iklan', 'jual'],
            ['adapter' => 'lowendtalk']
        );
    }

    protected function externalIdFromUrl(string $url): ?string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if (preg_match('~/(?:discussion|topic)/([0-9]+)~i', '/'.$path, $matches) === 1) {
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

    private function isAllowedCategoryUrl(string $url): bool
    {
        $path = strtolower(trim((string) parse_url($url, PHP_URL_PATH), '/'));
        if (! str_starts_with($path, 'categories/')) {
            return false;
        }

        $slug = trim(substr($path, strlen('categories/')), '/');

        return in_array($slug, self::ALLOWED_CATEGORIES, true);
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
}
