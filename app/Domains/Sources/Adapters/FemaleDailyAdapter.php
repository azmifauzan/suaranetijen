<?php

namespace App\Domains\Sources\Adapters;

use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\DiscoveryBatch;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceDocumentRef;
use DOMNode;
use DOMXPath;

class FemaleDailyAdapter extends AbstractHttpSourceAdapter
{
    /**
     * Femaledaily's own brand directory filter buckets — confirmed live
     * (6 Sep 2026): 26 letters plus a "0-9" bucket and a "#" bucket (both
     * non-empty, 10 and 27 brands respectively).
     *
     * @var list<string>
     */
    private const ALPHABETS = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
        'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
        '0-9', '#',
    ];

    protected function preflightUrl(): string
    {
        return rtrim((string) config('sources.femaledaily.base_url'), '/').'/';
    }

    /**
     * Discovery is a 3-level rotation: alphabet letter -> brand -> product
     * page. Each cycle does exactly one HTTP call (refresh the brand list for
     * a new letter, or fetch one product-listing page for the current
     * brand), same "one request per discover()" shape as every other
     * adapter, and every index always advances and wraps — never re-reads
     * the same position forever (the documented durable rule in
     * .ai/rules/adapters.md).
     */
    public function discover(CrawlCursor $cursor): DiscoveryBatch
    {
        $letterIndex = max(0, (int) ($cursor->metadata['letter_index'] ?? 0)) % count(self::ALPHABETS);
        $brands = $this->stringList($cursor->metadata['brands'] ?? []);

        if ($brands === []) {
            return $this->refreshBrandList($cursor, $letterIndex);
        }

        return $this->discoverBrandProducts($cursor, $letterIndex, $brands);
    }

    private function refreshBrandList(CrawlCursor $cursor, int $letterIndex): DiscoveryBatch
    {
        $letter = self::ALPHABETS[$letterIndex];
        $url = rtrim((string) config('sources.femaledaily.reviews_base_url'), '/')
            .'/brands?type=product&alphabet='.rawurlencode($letter).'&origin=&sort=name';

        $response = $this->request($url);
        $response->throw();
        $brands = $this->parseBrandSlugs($response->body());

        // An empty bucket (shouldn't happen for the confirmed-live 28
        // buckets above, but defensive against the site's own list
        // changing) skips straight to the next letter instead of retrying
        // the same empty bucket forever.
        if ($brands === []) {
            return new DiscoveryBatch(
                documents: [],
                nextCursor: new CrawlCursor(
                    sourceKey: $cursor->sourceKey,
                    cursorKey: $cursor->cursorKey,
                    cursorValue: 'letter_'.(($letterIndex + 1) % count(self::ALPHABETS)),
                    lastExternalId: $cursor->lastExternalId,
                    lastCrawledAt: now()->toImmutable(),
                    metadata: ['letter_index' => ($letterIndex + 1) % count(self::ALPHABETS), 'brands' => []]
                ),
                hasMore: false
            );
        }

        return new DiscoveryBatch(
            documents: [],
            nextCursor: new CrawlCursor(
                sourceKey: $cursor->sourceKey,
                cursorKey: $cursor->cursorKey,
                cursorValue: 'letter_'.$letterIndex.'_brand_0',
                lastExternalId: $cursor->lastExternalId,
                lastCrawledAt: now()->toImmutable(),
                metadata: [
                    'letter_index' => $letterIndex,
                    'brands' => $brands,
                    'brand_index' => 0,
                    'product_page' => 1,
                ]
            ),
            hasMore: true
        );
    }

    /**
     * @param  list<string>  $brands
     */
    private function discoverBrandProducts(CrawlCursor $cursor, int $letterIndex, array $brands): DiscoveryBatch
    {
        $brandIndex = max(0, (int) ($cursor->metadata['brand_index'] ?? 0)) % count($brands);
        $brand = $brands[$brandIndex];
        $page = max(1, (int) ($cursor->metadata['product_page'] ?? 1));

        $url = rtrim((string) config('sources.femaledaily.reviews_base_url'), '/')
            .'/brands/product/'.$brand.'?page='.$page;

        $response = $this->request($url);
        $response->throw();
        [$documents, $totalPages] = $this->parseBrandProducts($response->body(), $cursor->sourceKey, $brand);

        $nextPage = $page + 1;
        $nextBrandIndex = $brandIndex;
        $nextBrands = $brands;
        $nextLetterIndex = $letterIndex;

        if ($documents === [] || $page >= $totalPages) {
            $nextPage = 1;
            $nextBrandIndex = $brandIndex + 1;

            if ($nextBrandIndex >= count($brands)) {
                $nextBrandIndex = 0;
                $nextBrands = [];
                $nextLetterIndex = ($letterIndex + 1) % count(self::ALPHABETS);
            }
        }

        return new DiscoveryBatch(
            documents: $documents,
            nextCursor: new CrawlCursor(
                sourceKey: $cursor->sourceKey,
                cursorKey: $cursor->cursorKey,
                cursorValue: 'letter_'.$nextLetterIndex.'_brand_'.$nextBrandIndex.'_page_'.$nextPage,
                lastExternalId: $documents !== [] ? end($documents)->externalId : $cursor->lastExternalId,
                lastCrawledAt: now()->toImmutable(),
                metadata: [
                    'letter_index' => $nextLetterIndex,
                    'brands' => $nextBrands,
                    'brand_index' => $nextBrandIndex,
                    'product_page' => $nextPage,
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
        // `.text-content` is the review body paragraph only — deliberately
        // narrower than the surrounding `.review-content-wrapper`, which
        // also contains the reviewer's username ("X recommends this
        // product!"). Selecting the wrapper would leak third-party PII into
        // stored text; data minimization requires opinion text only.
        return $this->extractHtmlOpinions(
            $doc,
            [
                '//*[contains(concat(" ", normalize-space(@class), " "), " text-content ")]',
            ],
            [],
            ['adapter' => 'femaledaily']
        );
    }

    /**
     * @return list<string>
     */
    private function parseBrandSlugs(string $html): array
    {
        $dom = $this->loadHtml($html);
        if ($dom === null) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $links = $xpath->query('//a[@href]');
        if ($links === false) {
            return [];
        }

        $slugs = [];
        foreach ($links as $link) {
            if (! $link instanceof DOMNode) {
                continue;
            }

            $href = trim((string) $link->attributes?->getNamedItem('href')?->nodeValue);
            if (preg_match('~^/brands/product/([a-z0-9-]+)$~i', $href, $matches) === 1) {
                $slugs[$matches[1]] = true;
            }
        }

        return array_keys($slugs);
    }

    /**
     * @return array{0: list<SourceDocumentRef>, 1: int}
     */
    private function parseBrandProducts(string $html, string $sourceKey, string $brandSlug): array
    {
        $payload = $this->extractNextData($html);
        $products = $payload['props']['pageProps']['initialBrandProducts']['data'] ?? null;
        $totalPages = (int) ($payload['props']['pageProps']['initialBrandProducts']['pagination']['total_page'] ?? 1);

        if (! is_array($products)) {
            return [[], $totalPages];
        }

        $documents = [];
        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }

            $slug = $product['slug'] ?? null;
            $totalReview = (int) ($product['total_review'] ?? 0);
            if (! is_string($slug) || $slug === '' || $totalReview <= 0) {
                continue;
            }

            $categories = is_array($product['categories'] ?? null) ? $product['categories'] : [];
            [$rootSlug, $leafSlug] = $this->categoryPathSlugs($categories[0] ?? null);

            $documents[] = new SourceDocumentRef(
                sourceKey: $sourceKey,
                externalId: $brandSlug.':'.$slug,
                canonicalUrl: rtrim((string) config('sources.femaledaily.reviews_base_url'), '/')
                    ."/products/{$rootSlug}/{$leafSlug}/{$brandSlug}/{$slug}",
                title: is_string($product['name'] ?? null) && $product['name'] !== '' ? $product['name'] : null
            );
        }

        return [$documents, max(1, $totalPages)];
    }

    /**
     * Femaledaily's product URL routes purely on the trailing brand+product
     * slug (confirmed live: arbitrary placeholder category segments still
     * resolve the correct product) — these two segments only need to be
     * plausible, not exactly correct, so this walks to the outermost
     * category ancestor and pairs it with the immediate (leaf) category
     * rather than reproducing the full tree depth.
     *
     * @return array{0: string, 1: string}
     */
    private function categoryPathSlugs(mixed $category): array
    {
        if (! is_array($category) || ! is_string($category['slug'] ?? null) || $category['slug'] === '') {
            return ['produk', 'beauty'];
        }

        $leafSlug = $category['slug'];
        $root = $category;
        while (is_array($root['parent'] ?? null)) {
            $root = $root['parent'];
        }

        $rootSlug = is_string($root['slug'] ?? null) && $root['slug'] !== '' ? $root['slug'] : $leafSlug;

        return [$rootSlug, $leafSlug];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractNextData(string $html): array
    {
        $marker = '__NEXT_DATA__';
        $markerPos = strpos($html, $marker);
        if ($markerPos === false) {
            return [];
        }

        $start = strpos($html, '>', $markerPos);
        $end = strpos($html, '</script>', $markerPos);
        if ($start === false || $end === false || $end <= $start) {
            return [];
        }

        $json = substr($html, $start + 1, $end - $start - 1);
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
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
