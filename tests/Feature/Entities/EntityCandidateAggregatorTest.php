<?php

use App\Domains\Entities\Contracts\EntityCandidateSource;
use App\Domains\Entities\Models\EntityCandidate;
use App\Domains\Entities\Models\LlmSetting;
use App\Domains\Entities\Services\EntityCandidateAggregator;
use App\Domains\Entities\Services\EntityCandidateEnricher;
use App\Domains\Sources\Models\RawPayload;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Models\SourceItem;
use App\Domains\Sources\Models\UnmatchedMention;
use Illuminate\Support\Facades\Http;

function fakeCandidateSource(string $type, array $items): EntityCandidateSource
{
    return new class($type, $items) implements EntityCandidateSource
    {
        public function __construct(private string $type, private array $items) {}

        public function sourceType(): string
        {
            return $this->type;
        }

        public function discover(): array
        {
            return $this->items;
        }
    };
}

beforeEach(function () {
    LlmSetting::create(['base_url' => 'https://llm.internal/v1', 'model' => 'test-model', 'api_key' => 'key']);
    Http::preventStrayRequests();
    Http::fake(fn () => Http::response(['choices' => [['message' => ['content' => json_encode([
        'suggested_name' => 'Suggested',
        'suggested_entity_type' => 'product',
        'suggested_category' => 'Smartphone',
        'suggested_aliases' => [],
        'reasoning' => 'r',
    ])]]]]));
});

it('merges candidates across sources, sums weight, and creates one row', function () {
    $aggregator = new EntityCandidateAggregator(
        [
            fakeCandidateSource('search_query', [['raw_term' => 'iphone 17 pro', 'weight' => 5]]),
            fakeCandidateSource('google_trends', [['raw_term' => 'iPhone 17 Pro', 'weight' => 100]]),
        ],
        app(EntityCandidateEnricher::class)
    );

    $created = $aggregator->scan();

    expect($created)->toBe(1);
    $candidate = EntityCandidate::query()->first();
    expect($candidate->normalized_term)->toBe('iphone 17 pro')
        ->and($candidate->frequency_score)->toBe(105)
        ->and($candidate->source_types)->toEqualCanonicalizing(['search_query', 'google_trends'])
        ->and($candidate->status)->toBe('pending');
});

it('never resurfaces a term that already has an entity_candidates row', function () {
    EntityCandidate::factory()->rejected()->create(['normalized_term' => 'already seen']);

    $aggregator = new EntityCandidateAggregator(
        [fakeCandidateSource('search_query', [['raw_term' => 'already seen', 'weight' => 10]])],
        app(EntityCandidateEnricher::class)
    );

    $created = $aggregator->scan();

    expect($created)->toBe(0)
        ->and(EntityCandidate::query()->count())->toBe(1);
});

it('counts unmatched_mentions containing the candidate term as supporting evidence', function () {
    $source = Source::factory()->create();
    $item = SourceItem::factory()->create(['source_id' => $source->id]);
    UnmatchedMention::query()->create([
        'source_id' => $source->id,
        'source_item_id' => $item->id,
        'content_hash' => 'hash1',
        'reason' => 'entity_not_resolved',
    ]);
    RawPayload::query()->create([
        'source_id' => $source->id,
        'source_item_id' => $item->id,
        'payload_ref' => 'ref1',
        'payload' => 'Ada yang pernah coba Vivo X300 Ultra?',
        'content_type' => 'text/plain',
        'expires_at' => now()->addDay(),
    ]);

    $aggregator = new EntityCandidateAggregator(
        [fakeCandidateSource('search_query', [['raw_term' => 'vivo x300 ultra', 'weight' => 5]])],
        app(EntityCandidateEnricher::class)
    );
    $aggregator->scan();

    expect(EntityCandidate::query()->first()->unmatched_mention_count)->toBe(1);
});

it('continues scanning other sources when one source throws', function () {
    $throwingSource = new class implements EntityCandidateSource
    {
        public function sourceType(): string
        {
            return 'broken';
        }

        public function discover(): array
        {
            throw new RuntimeException('endpoint down');
        }
    };

    $aggregator = new EntityCandidateAggregator(
        [$throwingSource, fakeCandidateSource('search_query', [['raw_term' => 'still works', 'weight' => 5]])],
        app(EntityCandidateEnricher::class)
    );

    $created = $aggregator->scan();

    expect($created)->toBe(1)
        ->and(EntityCandidate::query()->where('normalized_term', 'still works')->exists())->toBeTrue();
});
