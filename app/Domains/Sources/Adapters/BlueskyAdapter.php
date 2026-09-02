<?php

namespace App\Domains\Sources\Adapters;

use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Sources\Contracts\CandidateOpinion;
use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\DiscoveryBatch;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceDocumentRef;

class BlueskyAdapter extends AbstractHttpSourceAdapter
{
    protected function preflightUrl(): string
    {
        return 'https://jetstream2.us-east.bsky.network/';
    }

    public function discover(CrawlCursor $cursor): DiscoveryBatch
    {
        $jetstreamUrl = (string) ($cursor->metadata['jetstream_url'] ?? 'https://jetstream2.us-east.bsky.network/subscribe');
        $query = [
            'wantedCollections' => 'app.bsky.feed.post',
        ];

        if ($cursor->cursorValue !== null) {
            $query['cursor'] = $cursor->cursorValue;
        }

        $response = $this->request($jetstreamUrl, $query);
        $response->throw();
        $documents = $this->parseEvents($response->body(), $cursor);

        $nextCursorValue = $documents !== []
            ? (string) ($documents[array_key_last($documents)]->metadata['time_us'] ?? $cursor->cursorValue)
            : $cursor->cursorValue;

        return new DiscoveryBatch(
            documents: $documents,
            nextCursor: new CrawlCursor(
                sourceKey: $cursor->sourceKey,
                cursorKey: $cursor->cursorKey,
                cursorValue: $nextCursorValue,
                lastExternalId: $documents !== [] ? end($documents)->externalId : $cursor->lastExternalId,
                lastCrawledAt: now()->toImmutable(),
                metadata: $cursor->metadata
            ),
            hasMore: $documents !== []
        );
    }

    public function fetch(SourceDocumentRef $ref): FetchedDocument
    {
        if (isset($ref->metadata['text'])) {
            return new FetchedDocument(
                ref: $ref,
                rawPayload: (string) json_encode($ref->metadata, JSON_THROW_ON_ERROR),
                contentType: 'application/json',
                fetchedAt: now()->toImmutable()
            );
        }

        return $this->fetchHttpDocument($ref);
    }

    public function extract(FetchedDocument $doc): iterable
    {
        $payload = json_decode($doc->rawPayload, true);
        $text = is_array($payload) && isset($payload['text']) && is_string($payload['text'])
            ? trim($payload['text'])
            : '';

        if ($text === '') {
            return [];
        }

        return [new CandidateOpinion(
            sourceKey: $doc->ref->sourceKey,
            externalItemId: $doc->ref->externalId,
            externalDocumentId: $doc->ref->externalId,
            canonicalUrl: $doc->ref->canonicalUrl,
            publishedAt: $doc->ref->publishedAt,
            text: $text,
            contentHash: hash('sha256', TextNormalizer::normalize($text)),
            metadata: ['adapter' => 'bluesky']
        )];
    }

    /**
     * @return list<SourceDocumentRef>
     */
    protected function parseEvents(string $payload, CrawlCursor $cursor): array
    {
        $lines = trim($payload) === '' ? [] : preg_split('/\R/u', trim($payload));
        if ($lines === false || $lines === []) {
            return [];
        }

        $aliases = array_values(array_filter(
            $cursor->metadata['aliases'] ?? [],
            static fn (mixed $alias): bool => is_string($alias) && trim($alias) !== ''
        ));
        $documents = [];

        foreach ($lines as $line) {
            $event = json_decode($line, true);
            if (! is_array($event)) {
                continue;
            }

            $commit = $event['commit'] ?? $event;
            if (! is_array($commit) || ($event['kind'] ?? 'commit') !== 'commit') {
                continue;
            }

            if (($commit['collection'] ?? null) !== 'app.bsky.feed.post') {
                continue;
            }

            $record = $commit['record'] ?? [];
            $text = is_array($record) && is_string($record['text'] ?? null) ? trim($record['text']) : '';
            if ($text === '' || ! $this->containsAlias($text, $aliases)) {
                continue;
            }

            $repo = (string) ($event['did'] ?? $commit['repo'] ?? '');
            $rkey = (string) ($commit['rkey'] ?? '');
            if ($repo === '' || $rkey === '') {
                continue;
            }

            $timeUs = $event['time_us'] ?? null;
            $publishedAt = is_array($record) ? $this->parseDate($record['createdAt'] ?? null) : null;
            $documents[] = new SourceDocumentRef(
                sourceKey: $cursor->sourceKey,
                externalId: $repo.'/'.$rkey,
                canonicalUrl: "https://bsky.app/profile/{$repo}/post/{$rkey}",
                title: null,
                publishedAt: $publishedAt,
                metadata: [
                    'text' => $text,
                    'repo' => $repo,
                    'rkey' => $rkey,
                    'time_us' => $timeUs,
                ]
            );
        }

        return $documents;
    }

    /**
     * @param  list<string>  $aliases
     */
    protected function containsAlias(string $text, array $aliases): bool
    {
        if ($aliases === []) {
            return true;
        }

        $normalizedText = TextNormalizer::normalize($text);
        foreach ($aliases as $alias) {
            $normalizedAlias = TextNormalizer::normalize($alias);
            if ($normalizedAlias !== '' && str_contains(" {$normalizedText} ", " {$normalizedAlias} ")) {
                return true;
            }
        }

        return false;
    }
}
