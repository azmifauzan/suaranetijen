<?php

namespace App\Domains\Sources\Adapters;

use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Sources\Contracts\CandidateOpinion;
use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\DiscoveryBatch;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceDocumentRef;
use Carbon\CarbonImmutable;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DetikAdapter extends AbstractHttpSourceAdapter
{
    private const COMMENT_QUERY = <<<'GRAPHQL'
        query search($type: String!, $size: Int!, $sort: String!, $page: Int!, $query: [ElasticSearchAggregation]) {
            search(type: $type, size: $size, sort: $sort, page: $page, query: $query) {
                paging
                counter
                hits {
                    results {
                        id
                        content
                        create_date
                        pin
                        child {
                            id
                            content
                            create_date
                        }
                    }
                }
            }
        }
        GRAPHQL;

    protected function preflightUrl(): string
    {
        return rtrim((string) config('sources.detik.base_url'), '/').'/';
    }

    public function discover(CrawlCursor $cursor): DiscoveryBatch
    {
        $desks = $this->stringList($cursor->metadata['desks'] ?? config('sources.detik.desks', []));
        if ($desks === []) {
            return new DiscoveryBatch([], null, false);
        }

        $deskCount = count($desks);
        $deskIndex = max(0, (int) ($cursor->metadata['desk_index'] ?? 0)) % $deskCount;
        $sitemapUrl = $desks[$deskIndex];

        $response = $this->request($sitemapUrl);
        $response->throw();
        $documents = $this->parseNewsSitemapDocuments($response->body(), $cursor->sourceKey);

        // A news sitemap always reflects "whatever is recent right now" — there
        // is no "exhausted" state to detect the way a paginated forum listing
        // has, so unlike KaskusAdapter/IndoForumAdapter's rotate-on-empty-page
        // pattern, this rotates to the next desk unconditionally every cycle.
        $nextDeskIndex = ($deskIndex + 1) % $deskCount;

        return new DiscoveryBatch(
            documents: $documents,
            nextCursor: new CrawlCursor(
                sourceKey: $cursor->sourceKey,
                cursorKey: $cursor->cursorKey,
                cursorValue: 'desk_'.$nextDeskIndex,
                lastExternalId: $documents !== [] ? end($documents)->externalId : $cursor->lastExternalId,
                lastCrawledAt: now()->toImmutable(),
                metadata: [
                    'desks' => $desks,
                    'desk_index' => $nextDeskIndex,
                ]
            ),
            hasMore: $documents !== []
        );
    }

    public function fetch(SourceDocumentRef $ref): FetchedDocument
    {
        if ($ref->canonicalUrl === null) {
            throw new RuntimeException("Source document [{$ref->externalId}] has no canonical URL.");
        }

        $articleResponse = $this->request($ref->canonicalUrl);
        $articleResponse->throw();

        $kanal = $this->extractKanal($articleResponse->body());
        $idArtikel = (int) $ref->externalId;
        $results = [];

        if ($kanal !== null && $idArtikel > 0) {
            $maxPages = max(1, (int) config('sources.detik.max_comment_pages', 3));

            for ($page = 1; $page <= $maxPages; $page++) {
                $commentResponse = $this->postCommentSearch($ref->canonicalUrl, $idArtikel, $page);
                $search = $commentResponse->json('data.search');
                if (! is_array($search)) {
                    break;
                }

                $pageResults = is_array($search['hits']['results'] ?? null) ? $search['hits']['results'] : [];
                foreach ($pageResults as $result) {
                    if (is_array($result)) {
                        $results[] = $result;
                    }
                }

                $totalPage = (int) ($search['paging']['total_page'] ?? 1);
                if ($page >= $totalPage) {
                    break;
                }
            }
        }

        return new FetchedDocument(
            ref: $ref,
            rawPayload: json_encode(['results' => $results], JSON_THROW_ON_ERROR),
            contentType: 'application/json',
            fetchedAt: CarbonImmutable::now()
        );
    }

    public function extract(FetchedDocument $doc): iterable
    {
        $payload = json_decode($doc->rawPayload, true);
        if (! is_array($payload)) {
            return [];
        }

        $results = is_array($payload['results'] ?? null) ? $payload['results'] : [];
        $comments = [];
        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $comments[] = $result;
            $children = is_array($result['child'] ?? null) ? $result['child'] : [];
            foreach ($children as $child) {
                if (is_array($child)) {
                    $comments[] = $child;
                }
            }
        }

        $opinions = [];
        $contentHashes = [];

        foreach ($comments as $comment) {
            $id = $comment['id'] ?? null;
            $rawText = $comment['content'] ?? null;
            $text = is_string($rawText) ? trim(strip_tags($rawText)) : '';

            if ($id === null || $text === '') {
                continue;
            }

            $normalizedText = TextNormalizer::normalize($text);
            if ($normalizedText === '') {
                continue;
            }

            $contentHash = hash('sha256', $normalizedText);
            if (isset($contentHashes[$contentHash])) {
                continue;
            }

            $contentHashes[$contentHash] = true;
            $opinions[] = new CandidateOpinion(
                sourceKey: $doc->ref->sourceKey,
                externalItemId: (string) $id,
                externalDocumentId: $doc->ref->externalId,
                canonicalUrl: $doc->ref->canonicalUrl,
                publishedAt: $doc->ref->publishedAt,
                text: $text,
                contentHash: $contentHash,
                metadata: ['adapter' => 'detik']
            );
        }

        return $opinions;
    }

    protected function externalIdFromUrl(string $url): ?string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if (preg_match('~/d-([0-9]+)~i', '/'.$path, $matches) === 1) {
            return $matches[1];
        }

        return parent::externalIdFromUrl($url);
    }

    /**
     * @return list<SourceDocumentRef>
     */
    private function parseNewsSitemapDocuments(string $payload, string $sourceKey): array
    {
        $dom = $this->loadXml($payload);
        if ($dom === null) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $urlNodes = $xpath->query('//*[local-name()="url"]');
        if ($urlNodes === false) {
            return [];
        }

        $documents = [];
        $seen = [];

        foreach ($urlNodes as $urlNode) {
            if (! $urlNode instanceof DOMNode) {
                continue;
            }

            $loc = $this->firstChildText($xpath, $urlNode, 'loc');
            if ($loc === '') {
                continue;
            }

            $externalId = $this->externalIdFromUrl($loc);
            if ($externalId === null || isset($seen[$externalId])) {
                continue;
            }

            $seen[$externalId] = true;

            $title = $this->firstChildText($xpath, $urlNode, 'title');
            $publicationDate = $this->firstChildText($xpath, $urlNode, 'publication_date');
            $publishedAt = $publicationDate !== '' ? $this->parseDate($publicationDate) : null;

            $documents[] = new SourceDocumentRef(
                sourceKey: $sourceKey,
                externalId: $externalId,
                canonicalUrl: $loc,
                title: $title !== '' ? $title : null,
                publishedAt: $publishedAt
            );
        }

        return $documents;
    }

    private function firstChildText(DOMXPath $xpath, DOMNode $context, string $localName): string
    {
        $nodes = $xpath->query('.//*[local-name()="'.$localName.'"]', $context);
        if ($nodes === false || $nodes->length === 0) {
            return '';
        }

        $node = $nodes->item(0);

        return $node instanceof DOMNode ? trim($node->textContent) : '';
    }

    /**
     * Detik embeds the comment widget's `kanal` (channel id) config in two
     * different templates across the network (an inline
     * `CommentComponent({idArtikel: X, kanal: Y, ...})` JS call on oto/hot
     * desks, a `<script data-itp-json="comment">{"kanal": Y, ...}</script>`
     * JSON blob on wolipop) — this matches either, since neither template
     * quotes the numeric value itself.
     */
    private function extractKanal(string $html): ?int
    {
        if (preg_match('/["\']?kanal["\']?\s*:\s*(\d+)/', $html, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function postCommentSearch(string $canonicalUrl, int $idArtikel, int $page, int $size = 20): Response
    {
        $host = (string) parse_url($canonicalUrl, PHP_URL_HOST);
        $origin = 'https://'.($host !== '' ? $host : 'www.detik.com');

        return Http::timeout(15)->withHeaders([
            'User-Agent' => 'SuaraNetijen/1.0 (+https://suaranetijen.id/sources)',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Origin' => $origin,
            'Referer' => $canonicalUrl,
        ])->post((string) config('sources.detik.comment_api_url'), [
            'query' => self::COMMENT_QUERY,
            'variables' => [
                'type' => 'comment',
                'sort' => 'newest',
                'size' => $size,
                'page' => $page,
                'query' => [
                    ['name' => 'news.artikel', 'terms' => $idArtikel],
                    ['name' => 'news.site', 'terms' => 'dtk'],
                ],
            ],
        ]);
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
