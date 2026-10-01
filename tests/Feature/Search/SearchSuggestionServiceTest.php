<?php

use App\Domains\Entities\Enums\EntityType;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Search\Models\SearchQuery;
use App\Domains\Search\Services\SearchSuggestionService;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

/**
 * @param  list<string>|int  $sessions
 */
function searchedBy(string $query, array|int $sessions, array $attributes = []): void
{
    foreach (is_int($sessions) ? range(1, $sessions) : $sessions as $session) {
        SearchQuery::factory()->create(array_merge([
            'query' => $query,
            'normalized_query' => $query,
            'result_count' => 5,
            'user_id' => null,
            'session_id' => "{$query}-{$session}",
        ], $attributes));
    }
}

function suggestionQueries(): array
{
    return collect(app(SearchSuggestionService::class)->getSuggestions())->pluck('query')->all();
}

test('a keyword counts once per visitor, so one session repeating it does not make it popular', function () {
    config()->set('search.suggestions.min_sessions', 3);

    foreach (range(1, 20) as $i) {
        SearchQuery::factory()->create(['query' => 'spam kata', 'normalized_query' => 'spam kata', 'result_count' => 5, 'session_id' => 'satu-sesi']);
    }
    searchedBy('vps murah', 3);

    expect(suggestionQueries())->toBe(['vps murah']);
});

test('only searches from the last 30 days with results count', function () {
    config()->set('search.suggestions.min_sessions', 1);

    searchedBy('lama sekali', 1, ['created_at' => now()->subDays(45)]);
    searchedBy('tanpa hasil', 1, ['result_count' => 0]);
    searchedBy('baru dan ada', 1);

    expect(suggestionQueries())->toBe(['baru dan ada']);
});

test('ranks by distinct visitors, then name', function () {
    config()->set('search.suggestions.min_sessions', 2);

    searchedBy('hp murah', 2);
    searchedBy('vps cepat', 4);
    searchedBy('bank digital', 3);

    expect(suggestionQueries())->toBe(['vps cepat', 'bank digital', 'hp murah']);
});

test('unsafe keywords never become a suggestion', function () {
    config()->set('search.suggestions.min_sessions', 1);
    config()->set('landing_pages.blocklist', ['judi']);

    $person = Entity::factory()->create(['name' => 'Budi Santoso', 'type' => EntityType::Person]);

    searchedBy('judi online', 1);
    searchedBy('hubungi 081234567890', 1);
    searchedBy('budi santoso korupsi', 1);
    searchedBy('vps murah', 1);

    expect(suggestionQueries())->toBe(['vps murah']);
});

test('shows six suggestions and fills with top-scoring entities only when keywords run short', function () {
    config()->set('search.suggestions.min_sessions', 1);

    $category = Category::factory()->create();
    $entity = Entity::factory()->create(['name' => 'Entitas Terbaik Lokal', 'category_id' => $category->id]);
    SentimentSnapshot::factory()->create(['entity_id' => $entity->id, 'period' => Period::OneYear->value, 'opinion_count' => 100, 'score' => 90.0]);
    $tiny = Entity::factory()->create(['name' => 'Terlalu Sedikit', 'category_id' => $category->id]);
    SentimentSnapshot::factory()->create(['entity_id' => $tiny->id, 'period' => Period::OneYear->value, 'opinion_count' => 5, 'score' => 100.0]);

    searchedBy('satu', 1);
    searchedBy('dua', 1);

    $suggestions = app(SearchSuggestionService::class)->getSuggestions();

    expect(collect($suggestions)->pluck('query')->all())->toBe(['dua', 'satu', 'Entitas Terbaik Lokal'])
        ->and(collect($suggestions)->pluck('source')->all())->toBe(['trending', 'trending', 'top_score']);

    Cache::flush();
    foreach (range(1, 8) as $n) {
        searchedBy("kata {$n}", 1);
    }

    expect(suggestionQueries())->toHaveCount(6);
});

test('the keyword list is cached for an hour', function () {
    config()->set('search.suggestions.min_sessions', 1);

    searchedBy('pertama', 1);
    expect(suggestionQueries())->toBe(['pertama']);

    searchedBy('kedua', 1);
    expect(suggestionQueries())->toBe(['pertama']);

    $this->travel(61)->minutes();
    expect(suggestionQueries())->toContain('kedua');
});

test('the homepage passes the suggestions to the page', function () {
    config()->set('search.suggestions.min_sessions', 1);
    searchedBy('vps murah', 1);

    $this->get(route('home'))->assertInertia(fn ($page) => $page
        ->where('searchSuggestions.0.query', 'vps murah')
        ->where('searchSuggestions.0.source', 'trending')
    );
});
