<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Ingestion\Jobs\DiscoverSourceDocumentsJob;
use App\Domains\Ingestion\Jobs\FetchSourceDocumentJob;
use App\Domains\Sentiment\Models\SentimentObservation;
use App\Domains\Sources\Adapters\YouTubeAdapter;
use App\Domains\Sources\Models\CrawlState;
use App\Domains\Sources\Models\Source;
use App\Domains\Sources\Services\SearchQueryPlanner;
use App\Domains\Sources\Services\SourceRateLimiter;
use App\Domains\Sources\Services\SourceRegistry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function plannedEntity(string $name, int $opinions = 0, ?int $lastOpinionDaysAgo = null): Entity
{
    $entity = Entity::factory()->create(['name' => $name]);
    SentimentObservation::factory()->count($opinions)->create([
        'entity_id' => $entity->id,
        'observed_at' => now()->subDays($lastOpinionDaysAgo ?? 0),
    ]);

    return $entity;
}

it('searches never-searched entities first, most recently active first', function () {
    $fading = plannedEntity('Produk Lama', 5, 200);
    $searched = plannedEntity('Sudah Dicari', 5, 0);
    $fresh = plannedEntity('Produk Baru', 1, 0);
    $planner = app(SearchQueryPlanner::class);
    $searchedAt = [$searched->id => now()->subYear()->getTimestamp()];

    expect($planner->next($searchedAt)['entity_id'])->toBe($fresh->id)
        ->and($planner->next($searchedAt + [$fresh->id => now()->getTimestamp()])['entity_id'])->toBe($fading->id);
});

it('searches an entity still being talked about more often than one fading from the sources', function () {
    $fading = plannedEntity('Produk Lama', 50, 200);
    $active = plannedEntity('Produk Baru', 50, 1);
    $planner = app(SearchQueryPlanner::class);

    // Fading: 200 idle days * 4h = 800h wait; active: clamped to the 24h minimum.
    $searchedAt = [$fading->id => now()->subDays(20)->getTimestamp(), $active->id => now()->subDays(2)->getTimestamp()];
    expect($planner->next($searchedAt)['entity_id'])->toBe($active->id);

    // Past the 90-day cap a fading entity is due again, so it is never dropped for good.
    $searchedAt = [$fading->id => now()->subDays(91)->getTimestamp(), $active->id => now()->getTimestamp()];
    expect($planner->next($searchedAt)['entity_id'])->toBe($fading->id);
});

it('speeds a fading entity back up once a fresh opinion lands', function () {
    $revived = plannedEntity('Produk Viral Lagi', 10, 200);
    $quiet = plannedEntity('Produk Sepi', 10, 100);
    $planner = app(SearchQueryPlanner::class);
    $searchedAt = [$revived->id => now()->subDays(5)->getTimestamp(), $quiet->id => now()->subDays(20)->getTimestamp()];

    expect($planner->next($searchedAt)['entity_id'])->toBe($quiet->id);

    SentimentObservation::factory()->create(['entity_id' => $revived->id, 'observed_at' => now()]);

    expect($planner->next($searchedAt)['entity_id'])->toBe($revived->id);
});

it('picks up an entity added after earlier searches on the next call', function () {
    $old = plannedEntity('Lama');
    $planner = app(SearchQueryPlanner::class);
    $searchedAt = [$old->id => now()->getTimestamp()];

    $added = plannedEntity('Baru Ditambah');

    expect($planner->next($searchedAt))->toBe(['entity_id' => $added->id, 'term' => 'Baru Ditambah']);
});

it('returns null when no entity has a usable search term', function () {
    expect(app(SearchQueryPlanner::class)->next([]))->toBeNull();
});

it('searches one entity per YouTube cycle, only page 1, and moves to the next entity', function () {
    config(['sources.youtube.api_key' => 'test-key']);
    Queue::fake([FetchSourceDocumentJob::class]);
    Http::preventStrayRequests();
    Http::fake(['*/youtube/v3/search*' => Http::response([
        'items' => [['id' => ['videoId' => 'vid1'], 'snippet' => ['title' => 't']]],
        'nextPageToken' => 'MORE',
    ])]);

    $first = plannedEntity('Alpha');
    $second = plannedEntity('Beta', 3);
    $source = Source::factory()->create(['key' => 'youtube', 'adapter' => YouTubeAdapter::class]);

    foreach ([1, 2, 3] as $cycle) {
        app(DiscoverSourceDocumentsJob::class, ['source' => $source])
            ->handle(app(SourceRegistry::class), app(SourceRateLimiter::class));
    }

    $queries = Http::recorded()->map(function ($pair) {
        parse_str((string) parse_url($pair[0]->url(), PHP_URL_QUERY), $q);

        return $q;
    });

    expect($queries->pluck('q')->all())->toBe(['Alpha', 'Beta', 'Alpha'])
        ->and($queries->pluck('pageToken')->filter()->all())->toBe([])
        ->and(array_keys(CrawlState::where('source_id', $source->id)->first()->metadata['searched_at']))
        ->toEqualCanonicalizing([$first->id, $second->id]);
});
