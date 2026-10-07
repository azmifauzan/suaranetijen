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

function plannedEntity(string $name, int $opinions = 0): Entity
{
    $entity = Entity::factory()->create(['name' => $name]);
    SentimentObservation::factory()->count($opinions)->create(['entity_id' => $entity->id]);

    return $entity;
}

it('searches never-searched entities first, fewest opinions first', function () {
    plannedEntity('Banyak Data', 5);
    $empty = plannedEntity('Tanpa Data', 0);

    expect(app(SearchQueryPlanner::class)->next([])['entity_id'])->toBe($empty->id);
});

it('delays an entity with data longer than one without after both were searched', function () {
    $rich = plannedEntity('Banyak Data', 10);
    $poor = plannedEntity('Sedikit Data', 1);
    $searchedAt = [$rich->id => now()->subDay()->getTimestamp(), $poor->id => now()->subDay()->getTimestamp()];

    expect(app(SearchQueryPlanner::class)->next($searchedAt)['entity_id'])->toBe($poor->id);
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
