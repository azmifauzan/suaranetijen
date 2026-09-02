<?php

namespace App\Domains\Sources\Adapters;

use App\Domains\Sources\Contracts\CandidateOpinion;
use App\Domains\Sources\Contracts\CrawlCursor;
use App\Domains\Sources\Contracts\DiscoveryBatch;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Contracts\SourceAdapter;
use App\Domains\Sources\Contracts\SourceDocumentRef;
use App\Domains\Sources\Contracts\SourceHealth;
use Carbon\CarbonImmutable;
use RuntimeException;

class FakeSourceAdapter implements SourceAdapter
{
    protected static ?SourceHealth $customHealth = null;

    protected static bool $simulateFetchFailure = false;

    public static function setHealth(SourceHealth $health): void
    {
        self::$customHealth = $health;
    }

    public static function reset(): void
    {
        self::$customHealth = null;
        self::$simulateFetchFailure = false;
    }

    public static function setSimulateFetchFailure(bool $fail): void
    {
        self::$simulateFetchFailure = $fail;
    }

    public function preflight(): SourceHealth
    {
        return self::$customHealth ?? SourceHealth::healthy('Fake adapter is ready and operational.');
    }

    public function discover(CrawlCursor $cursor): DiscoveryBatch
    {
        $currentPage = (int) ($cursor->metadata['page'] ?? 1);
        $nextPage = $currentPage + 1;

        $doc1 = new SourceDocumentRef(
            sourceKey: $cursor->sourceKey,
            externalId: "thread-{$cursor->sourceKey}-page-{$currentPage}-1",
            canonicalUrl: "https://example.com/{$cursor->sourceKey}/threads/1",
            title: "Diskusi Layanan dan Performa {$cursor->sourceKey} 1",
            publishedAt: CarbonImmutable::now()->subHours(5),
            metadata: ['page' => $currentPage]
        );

        $doc2 = new SourceDocumentRef(
            sourceKey: $cursor->sourceKey,
            externalId: "thread-{$cursor->sourceKey}-page-{$currentPage}-2",
            canonicalUrl: "https://example.com/{$cursor->sourceKey}/threads/2",
            title: "Pengalaman Pemakaian Layanan {$cursor->sourceKey} 2",
            publishedAt: CarbonImmutable::now()->subHours(2),
            metadata: ['page' => $currentPage]
        );

        $nextCursor = new CrawlCursor(
            sourceKey: $cursor->sourceKey,
            cursorKey: $cursor->cursorKey,
            cursorValue: "page_{$nextPage}",
            lastExternalId: $doc2->externalId,
            lastCrawledAt: CarbonImmutable::now(),
            metadata: ['page' => $nextPage]
        );

        return new DiscoveryBatch(
            documents: [$doc1, $doc2],
            nextCursor: $nextCursor,
            hasMore: $currentPage < 5
        );
    }

    public function fetch(SourceDocumentRef $ref): FetchedDocument
    {
        if (self::$simulateFetchFailure) {
            throw new RuntimeException("Simulated network/fetch failure for [{$ref->externalId}].");
        }

        $html = "<html><body><article><h1>{$ref->title}</h1><div class='post'>VPS Biznet Gio sangat stabil dan bandwidth tanpa kuota. Sangat puas pakai layanan ini.</div><div class='post'>Samsung Galaxy A57 kameranya jernih dan baterai tahan lama.</div></article></body></html>";

        return new FetchedDocument(
            ref: $ref,
            rawPayload: $html,
            contentType: 'text/html',
            fetchedAt: CarbonImmutable::now()
        );
    }

    public function extract(FetchedDocument $doc): iterable
    {
        $opinion1 = new CandidateOpinion(
            sourceKey: $doc->ref->sourceKey,
            externalItemId: "item-{$doc->ref->externalId}-1",
            externalDocumentId: $doc->ref->externalId,
            canonicalUrl: $doc->ref->canonicalUrl,
            publishedAt: $doc->ref->publishedAt,
            text: 'VPS Biznet Gio sangat stabil dan bandwidth tanpa kuota. Sangat puas pakai layanan ini.',
            contentHash: hash('sha256', 'VPS Biznet Gio sangat stabil dan bandwidth tanpa kuota. Sangat puas pakai layanan ini.')
        );

        $opinion2 = new CandidateOpinion(
            sourceKey: $doc->ref->sourceKey,
            externalItemId: "item-{$doc->ref->externalId}-2",
            externalDocumentId: $doc->ref->externalId,
            canonicalUrl: $doc->ref->canonicalUrl,
            publishedAt: $doc->ref->publishedAt,
            text: 'Samsung Galaxy A57 kameranya jernih dan baterai tahan lama.',
            contentHash: hash('sha256', 'Samsung Galaxy A57 kameranya jernih dan baterai tahan lama.')
        );

        return [$opinion1, $opinion2];
    }
}
