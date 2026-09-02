<?php

namespace App\Domains\Sources\Adapters;

use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Sources\Contracts\CandidateOpinion;
use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\DiscoveryBatch;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceDocumentRef;
use App\Domains\Sources\Contracts\SourceHealth;
use Carbon\CarbonImmutable;
use RuntimeException;

class YouTubeAdapter extends AbstractHttpSourceAdapter
{
    protected function preflightUrl(): string
    {
        return $this->apiUrl('videos');
    }

    /**
     * @return array<string, scalar>
     */
    protected function preflightQuery(): array
    {
        return [
            'part' => 'id',
            'id' => 'dQw4w9WgXcQ',
            'key' => $this->apiKey(),
        ];
    }

    public function preflight(): SourceHealth
    {
        if ($this->apiKey() === '') {
            return SourceHealth::policyDisabled(
                'YouTube Data API is policy_disabled until YOUTUBE_API_KEY is configured.',
                ['reason' => 'missing_api_key']
            );
        }

        return parent::preflight();
    }

    public function discover(CrawlCursor $cursor): DiscoveryBatch
    {
        if ($this->apiKey() === '') {
            throw new RuntimeException('YouTube Data API key is not configured.');
        }

        $queries = $this->queries($cursor);
        $queryIndex = max(0, (int) ($cursor->metadata['query_index'] ?? 0));
        $query = trim((string) ($queries[$queryIndex] ?? ''));

        if ($query === '') {
            return new DiscoveryBatch([], null, false);
        }

        $parameters = [
            'part' => 'id,snippet',
            'q' => $query,
            'type' => 'video',
            'maxResults' => min(50, max(1, (int) ($cursor->metadata['max_results'] ?? config('sources.youtube.max_results', 50)))),
            'regionCode' => 'ID',
            'relevanceLanguage' => 'id',
            'key' => $this->apiKey(),
        ];

        if ($cursor->cursorValue !== null) {
            $parameters['pageToken'] = $cursor->cursorValue;
        }

        $response = $this->request($this->apiUrl('search'), $parameters);
        $response->throw();
        $payload = $response->json();
        $documents = [];

        $items = is_array($payload) && is_array($payload['items'] ?? null) ? $payload['items'] : [];

        foreach ($items as $item) {
            if (! is_array($item) || ! is_array($item['id'] ?? null)) {
                continue;
            }

            $videoId = $item['id']['videoId'] ?? null;
            if (! is_string($videoId) || trim($videoId) === '') {
                continue;
            }

            $videoId = trim($videoId);

            $snippet = is_array($item['snippet'] ?? null) ? $item['snippet'] : [];
            $publishedAt = is_string($snippet['publishedAt'] ?? null)
                ? $this->parseDate($snippet['publishedAt'])
                : null;
            $documents[] = new SourceDocumentRef(
                sourceKey: $cursor->sourceKey,
                externalId: $videoId,
                canonicalUrl: 'https://www.youtube.com/watch?v='.$videoId,
                title: is_string($snippet['title'] ?? null) ? trim($snippet['title']) : null,
                publishedAt: $publishedAt,
                metadata: ['query' => $query]
            );
        }

        $nextPageToken = is_array($payload) && is_string($payload['nextPageToken'] ?? null)
            ? trim($payload['nextPageToken'])
            : '';
        $nextQueryIndex = $nextPageToken === '' ? $queryIndex + 1 : $queryIndex;
        $hasMore = $nextPageToken !== '' || $nextQueryIndex < count($queries);
        $nextCursor = null;

        if ($hasMore) {
            $nextMetadata = [
                ...$cursor->metadata,
                'query_index' => $nextQueryIndex,
            ];

            $nextCursor = new CrawlCursor(
                sourceKey: $cursor->sourceKey,
                cursorKey: $cursor->cursorKey,
                cursorValue: $nextPageToken !== '' ? $nextPageToken : null,
                lastExternalId: $documents !== [] ? end($documents)->externalId : $cursor->lastExternalId,
                lastCrawledAt: now()->toImmutable(),
                metadata: $nextMetadata
            );
        }

        return new DiscoveryBatch($documents, $nextCursor, $hasMore);
    }

    public function fetch(SourceDocumentRef $ref): FetchedDocument
    {
        if ($this->apiKey() === '') {
            throw new RuntimeException('YouTube Data API key is not configured.');
        }

        $maxPages = max(1, (int) ($ref->metadata['max_comment_pages'] ?? config('sources.youtube.max_comment_pages', 3)));
        $pageToken = null;
        $items = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            $parameters = [
                'part' => 'snippet',
                'videoId' => $ref->externalId,
                'maxResults' => 100,
                'textFormat' => 'plainText',
                'key' => $this->apiKey(),
            ];

            if ($pageToken !== null) {
                $parameters['pageToken'] = $pageToken;
            }

            $response = $this->request($this->apiUrl('commentThreads'), $parameters);
            $response->throw();
            $payload = $response->json();

            if (! is_array($payload)) {
                break;
            }

            $pageItems = is_array($payload['items'] ?? null) ? $payload['items'] : [];
            foreach ($pageItems as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }

            $pageToken = is_string($payload['nextPageToken'] ?? null)
                ? trim($payload['nextPageToken'])
                : '';

            if ($pageToken === '') {
                break;
            }
        }

        return new FetchedDocument(
            ref: $ref,
            rawPayload: json_encode(['videoId' => $ref->externalId, 'items' => $items], JSON_THROW_ON_ERROR),
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

        $opinions = [];
        $contentHashes = [];

        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $snippet = $item['snippet']['topLevelComment']['snippet'] ?? $item['snippet'] ?? null;
            if (! is_array($snippet)) {
                continue;
            }

            $rawText = $snippet['textOriginal'] ?? $snippet['textDisplay'] ?? null;
            $text = is_string($rawText) ? trim(strip_tags($rawText)) : '';
            if ($text === '') {
                continue;
            }

            $contentHash = hash('sha256', TextNormalizer::normalize($text));
            if (isset($contentHashes[$contentHash])) {
                continue;
            }

            $contentHashes[$contentHash] = true;
            $opinions[] = new CandidateOpinion(
                sourceKey: $doc->ref->sourceKey,
                externalItemId: is_string($item['id'] ?? null) ? $item['id'] : $doc->ref->externalId.'-comment-'.$index,
                externalDocumentId: $doc->ref->externalId,
                canonicalUrl: $doc->ref->canonicalUrl,
                publishedAt: is_string($snippet['publishedAt'] ?? null)
                    ? $this->parseDate($snippet['publishedAt'])
                    : null,
                text: $text,
                contentHash: $contentHash,
                metadata: ['adapter' => 'youtube', 'video_id' => $doc->ref->externalId]
            );
        }

        return $opinions;
    }

    private function apiKey(): string
    {
        return trim((string) config('sources.youtube.api_key'));
    }

    private function apiUrl(string $resource): string
    {
        return rtrim((string) config('sources.youtube.api_url'), '/').'/'.$resource;
    }

    /**
     * @return list<string>
     */
    private function queries(CrawlCursor $cursor): array
    {
        $configured = $cursor->metadata['queries'] ?? $cursor->metadata['aliases'] ?? [];
        $queries = is_array($configured) ? $configured : [];

        if (is_string($cursor->metadata['query'] ?? null) && trim($cursor->metadata['query']) !== '') {
            $queries = [$cursor->metadata['query']];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $query): string => is_string($query) ? trim($query) : '', $queries),
            static fn (string $query): bool => $query !== ''
        )));
    }
}
