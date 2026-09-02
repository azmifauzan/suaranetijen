<?php

namespace App\Domains\Sources\Adapters;

use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Sources\Contracts\CandidateOpinion;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceAdapter;
use App\Domains\Sources\Contracts\SourceDocumentRef;
use App\Domains\Sources\Contracts\SourceHealth;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

abstract class AbstractHttpSourceAdapter implements SourceAdapter
{
    /**
     * Return the public URL used to check source availability.
     */
    abstract protected function preflightUrl(): string;

    public function preflight(): SourceHealth
    {
        $startedAt = microtime(true);

        try {
            $response = $this->request($this->preflightUrl());
            $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

            return match (true) {
                $response->status() === 401 || $response->status() === 403 => SourceHealth::policyDisabled(
                    'Source access is not available without authentication or an allowed public request.',
                    ['http_status' => $response->status()]
                ),
                $response->status() === 429 => SourceHealth::quotaLimited(
                    'Source rate limit was reached during preflight.',
                    ['http_status' => $response->status()]
                ),
                $response->successful() => SourceHealth::healthy(
                    'Source preflight passed successfully.',
                    $responseTimeMs,
                    ['http_status' => $response->status()]
                ),
                default => SourceHealth::degraded(
                    "Source returned HTTP status {$response->status()} during preflight.",
                    $responseTimeMs,
                    ['http_status' => $response->status()]
                ),
            };
        } catch (Throwable $exception) {
            return SourceHealth::blocked(
                'Source preflight failed: '.$exception->getMessage(),
                (int) round((microtime(true) - $startedAt) * 1000),
                ['exception' => $exception::class]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function request(string $url, array $query = []): Response
    {
        return Http::timeout(15)
            ->withHeaders([
                'User-Agent' => 'SuaraNetijen/1.0 (+https://suaranetijen.id/sources)',
            ])
            ->get($url, $query);
    }

    protected function fetchHttpDocument(SourceDocumentRef $ref): FetchedDocument
    {
        if ($ref->canonicalUrl === null) {
            throw new RuntimeException("Source document [{$ref->externalId}] has no canonical URL.");
        }

        $response = $this->request($ref->canonicalUrl);
        $response->throw();

        return new FetchedDocument(
            ref: $ref,
            rawPayload: $response->body(),
            contentType: $response->header('Content-Type') ?: 'text/html',
            fetchedAt: CarbonImmutable::now()
        );
    }

    /**
     * @param  list<string>  $selectors
     * @param  list<string>  $excludedTerms
     * @param  array<string, mixed>  $metadata
     * @return list<CandidateOpinion>
     */
    protected function extractHtmlOpinions(
        FetchedDocument $document,
        array $selectors,
        array $excludedTerms = [],
        array $metadata = []
    ): array {
        $dom = $this->loadHtml($document->rawPayload);
        if ($dom === null) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $nodes = $this->firstMatchingNodes($xpath, $selectors);
        $opinions = [];
        $contentHashes = [];

        foreach ($nodes as $index => $node) {
            $text = $this->cleanNodeText($xpath, $node);
            $normalizedText = TextNormalizer::normalize($text);

            if ($normalizedText === '' || $this->containsExcludedTerm($normalizedText, $excludedTerms)) {
                continue;
            }

            $contentHash = hash('sha256', $normalizedText);
            if (isset($contentHashes[$contentHash])) {
                continue;
            }

            $contentHashes[$contentHash] = true;
            $itemId = $this->nodeIdentifier($node) ?? "{$document->ref->externalId}-{$index}";

            $opinions[] = new CandidateOpinion(
                sourceKey: $document->ref->sourceKey,
                externalItemId: $itemId,
                externalDocumentId: $document->ref->externalId,
                canonicalUrl: $document->ref->canonicalUrl,
                publishedAt: $document->ref->publishedAt,
                text: $text,
                contentHash: $contentHash,
                metadata: $metadata
            );
        }

        return $opinions;
    }

    /**
     * @param  list<string>  $selectors
     * @return list<DOMNode>
     */
    protected function firstMatchingNodes(DOMXPath $xpath, array $selectors): array
    {
        foreach ($selectors as $selector) {
            $matches = $xpath->query($selector);
            if ($matches === false || $matches->length === 0) {
                continue;
            }

            $nodes = [];
            foreach ($matches as $node) {
                if ($node instanceof DOMNode) {
                    $nodes[] = $node;
                }
            }

            if ($nodes !== []) {
                return $nodes;
            }
        }

        return [];
    }

    protected function cleanNodeText(DOMXPath $xpath, DOMNode $node): string
    {
        $removable = $xpath->query(
            './/blockquote | .//script | .//style | .//nav | .//form | .//header | .//footer | '
            .'.//*[contains(@class, "quote")] | .//*[contains(@class, "signature")] | '
            .'.//*[contains(@class, "promo")] | .//*[contains(@class, "advert")] | '
            .'.//*[contains(@class, "navigation")] | .//*[contains(@id, "signature")]'
        );

        if ($removable !== false) {
            foreach (iterator_to_array($removable) as $child) {
                if ($child instanceof DOMNode && $child->parentNode !== null) {
                    $child->parentNode->removeChild($child);
                }
            }
        }

        return trim((string) preg_replace('/\s+/u', ' ', $node->textContent ?? ''));
    }

    /**
     * @param  list<string>  $excludedTerms
     */
    protected function containsExcludedTerm(string $normalizedText, array $excludedTerms): bool
    {
        foreach ($excludedTerms as $term) {
            $normalizedTerm = TextNormalizer::normalize($term);
            if ($normalizedTerm !== '' && str_contains(" {$normalizedText} ", " {$normalizedTerm} ")) {
                return true;
            }
        }

        return false;
    }

    protected function nodeIdentifier(DOMNode $node): ?string
    {
        if (! $node instanceof DOMElement || ! $node->hasAttributes()) {
            return null;
        }

        foreach (['data-post-id', 'data-id', 'id'] as $attribute) {
            $value = trim($node->getAttribute($attribute));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return list<SourceDocumentRef>
     */
    protected function parseFeedDocuments(string $payload, string $sourceKey): array
    {
        $dom = $this->loadXml($payload);
        if ($dom === null) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $items = $xpath->query('//item | //entry');
        if ($items === false) {
            return [];
        }

        $documents = [];
        $seen = [];
        foreach ($items as $item) {
            if (! $item instanceof DOMNode) {
                continue;
            }

            $title = $this->childText($xpath, $item, 'title');
            $link = $this->childText($xpath, $item, 'link');
            $guid = $this->childText($xpath, $item, 'guid') ?? $this->childText($xpath, $item, 'id');
            $url = $link !== null && filter_var($link, FILTER_VALIDATE_URL) !== false ? $link : $guid;

            if ($url === null || $url === '') {
                continue;
            }

            $externalId = $guid ?: $this->externalIdFromUrl($url);
            if ($externalId === null || isset($seen[$externalId])) {
                continue;
            }

            $seen[$externalId] = true;
            $documents[] = new SourceDocumentRef(
                sourceKey: $sourceKey,
                externalId: $externalId,
                canonicalUrl: $url,
                title: $title !== null ? trim($title) : null,
                publishedAt: $this->parseDate(
                    $this->childText($xpath, $item, 'pubDate')
                    ?? $this->childText($xpath, $item, 'published')
                    ?? $this->childText($xpath, $item, 'updated')
                )
            );
        }

        return $documents;
    }

    /**
     * @return list<SourceDocumentRef>
     */
    protected function parseHtmlDocumentLinks(
        string $payload,
        string $sourceKey,
        string $baseUrl,
        string $hrefPattern
    ): array {
        $dom = $this->loadHtml($payload);
        if ($dom === null) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $links = $xpath->query('//a[@href]');
        if ($links === false) {
            return [];
        }

        $documents = [];
        $seen = [];
        foreach ($links as $link) {
            if (! $link instanceof DOMNode) {
                continue;
            }

            $href = trim((string) $link->attributes?->getNamedItem('href')?->nodeValue);
            if ($href === '' || preg_match($hrefPattern, $href) !== 1) {
                continue;
            }

            $url = $this->absoluteUrl($href, $baseUrl);
            $externalId = $this->externalIdFromUrl($url);
            if ($externalId === null || isset($seen[$externalId])) {
                continue;
            }

            $seen[$externalId] = true;
            $title = trim((string) preg_replace('/\s+/u', ' ', $link->textContent ?? ''));
            $documents[] = new SourceDocumentRef(
                sourceKey: $sourceKey,
                externalId: $externalId,
                canonicalUrl: $url,
                title: $title !== '' ? $title : null
            );
        }

        return $documents;
    }

    protected function childText(DOMXPath $xpath, DOMNode $parent, string $name): ?string
    {
        $matches = $xpath->query('./*[local-name()="'.$name.'"]', $parent);
        if ($matches === false || $matches->length === 0) {
            return null;
        }

        $firstMatch = $matches->item(0);
        if (! $firstMatch instanceof DOMElement) {
            return null;
        }

        $text = trim($firstMatch->textContent);

        return $text !== '' ? $text : null;
    }

    protected function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    protected function externalIdFromUrl(string $url): ?string
    {
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        foreach (['t', 'p', 'post', 'id'] as $key) {
            if (isset($query[$key]) && is_scalar($query[$key]) && (string) $query[$key] !== '') {
                return (string) $query[$key];
            }
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path === '') {
            return null;
        }

        if (preg_match('/(?:^|\/)[^\/]*\.([0-9]+)(?:$|\/)/', $path, $matches) === 1) {
            return $matches[1];
        }

        $segments = explode('/', $path);
        $lastSegment = end($segments);
        $lastSegment = preg_replace('/\.[a-z0-9]+$/i', '', $lastSegment);

        return $lastSegment !== null && $lastSegment !== '' ? $lastSegment : null;
    }

    protected function absoluteUrl(string $url, string $baseUrl): string
    {
        if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
            return $url;
        }

        $base = parse_url($baseUrl);
        $origin = ($base['scheme'] ?? 'https').'://'.($base['host'] ?? '');

        if (str_starts_with($url, '//')) {
            return ($base['scheme'] ?? 'https').':'.$url;
        }

        if (str_starts_with($url, '/')) {
            return rtrim($origin, '/').'/'.ltrim($url, '/');
        }

        $basePath = (string) ($base['path'] ?? '/');
        $directory = str_ends_with($basePath, '/')
            ? $basePath
            : substr($basePath, 0, strrpos($basePath, '/') + 1);
        $relativePath = str_starts_with($url, './') ? substr($url, 2) : $url;

        return rtrim($origin, '/').rtrim($directory, '/').'/'.ltrim($relativePath, '/');
    }

    protected function pageUrl(string $url, int $page): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $page > 1 ? $url.$separator.'page='.$page : $url;
    }

    protected function offsetUrl(string $url, int $page, int $perPage = 50): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $page > 1 ? $url.$separator.'start='.(($page - 1) * $perPage) : $url;
    }

    protected function loadHtml(string $payload): ?DOMDocument
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($payload, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $dom : null;
    }

    protected function loadXml(string $payload): ?DOMDocument
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($payload, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $dom : null;
    }
}
