<?php

use App\Domains\Ingestion\Jobs\ExtractCandidateOpinionsJob;
use App\Domains\Ingestion\Jobs\MatchEntitiesJob;
use App\Domains\Sources\Adapters\FakeSourceAdapter;
use App\Domains\Sources\Contracts\CandidateOpinion;
use App\Domains\Sources\Contracts\FetchedDocument;
use App\Domains\Sources\Enums\ProcessingState;
use App\Domains\Sources\Models\RawPayload;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceDocument;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Services\RawPayloadStorage;
use App\Domains\Sources\Services\SourceRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class ManyOpinionsFakeAdapter extends FakeSourceAdapter
{
    public function extract(FetchedDocument $doc): iterable
    {
        foreach (range(1, 60) as $i) {
            yield new CandidateOpinion(
                sourceKey: $doc->ref->sourceKey,
                externalItemId: "comment-{$i}",
                externalDocumentId: $doc->ref->externalId,
                publishedAt: CarbonImmutable::parse('2026-09-01 10:00:00'),
                text: "Komentar nomor {$i} tentang layanan ini sangat memuaskan.",
                contentHash: hash('sha256', "komentar {$i}")
            );
        }
    }
}

function runExtract(SourceDocument $document): void
{
    (new ExtractCandidateOpinionsJob($document, '{}'))->handle(app(SourceRegistry::class), app(RawPayloadStorage::class));
}

function extractFixture(): array
{
    $source = Source::factory()->create([
        'adapter' => ManyOpinionsFakeAdapter::class,
        'retention_policy' => ['raw_ttl_hours' => 24],
    ]);
    $document = SourceDocument::factory()->create(['source_id' => $source->id]);

    return [$source, $document];
}

it('stores an item and payload per opinion and queues matching for each', function () {
    Queue::fake();
    [$source, $document] = extractFixture();

    runExtract($document);

    $items = SourceItem::query()->where('source_id', $source->id)->get();
    expect($items)->toHaveCount(60)
        ->and($items->every(fn (SourceItem $item) => $item->source_document_id === $document->id
            && $item->processing_state === ProcessingState::Pending
            && $item->raw_payload_ref !== null
            && $item->expires_at !== null))->toBeTrue()
        ->and($items->first()->published_at->toDateTimeString())->toBe('2026-09-01 10:00:00')
        ->and(RawPayload::query()->where('source_id', $source->id)->count())->toBe(60)
        ->and(RawPayload::query()->where('payload_ref', $items->first()->raw_payload_ref)->value('payload'))
        ->toContain('Komentar nomor');

    Queue::assertPushed(MatchEntitiesJob::class, 60);
});

it('is idempotent when the same document is extracted again after a retry', function () {
    Queue::fake();
    [$source, $document] = extractFixture();

    runExtract($document);
    $first = SourceItem::query()->where('source_id', $source->id)->orderBy('id')->pluck('raw_payload_ref', 'id');
    SourceItem::query()->where('source_id', $source->id)->update(['processing_state' => ProcessingState::Processed]);

    runExtract($document);

    expect(SourceItem::query()->where('source_id', $source->id)->count())->toBe(60)
        ->and(RawPayload::query()->where('source_id', $source->id)->count())->toBe(60)
        ->and(SourceItem::query()->where('source_id', $source->id)->orderBy('id')->pluck('raw_payload_ref', 'id'))->toEqual($first)
        ->and(SourceItem::query()->where('source_id', $source->id)->where('processing_state', ProcessingState::Pending)->count())->toBe(60);

    Queue::assertPushed(MatchEntitiesJob::class, 120);
});

it('does not issue database queries per opinion', function () {
    Queue::fake();
    [, $document] = extractFixture();

    DB::enableQueryLog();
    runExtract($document);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect(SourceItem::query()->count())->toBe(60);

    // Workers reach Postgres over the network (~35ms per round trip): a per-opinion
    // loop of 60 opinions blew the 60s job timeout on large YouTube pages.
    expect($queries)->toBeLessThan(20);
});
