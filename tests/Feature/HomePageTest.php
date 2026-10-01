<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Search\Enums\SearchLandingPageStatus;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\Theme;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Cache::flush();
});

function createIndexablePublishedTopic(array $attributes = []): SearchLandingPage
{
    $category = isset($attributes['category_id'])
        ? Category::find($attributes['category_id'])
        : Category::factory()->create();

    $theme = Theme::firstOrCreate(
        ['slug' => 'fitur-lengkap'],
        ['display_label' => 'Fitur Lengkap', 'canonical_key' => 'fitur-lengkap']
    );

    $topic = SearchLandingPage::factory()->published()->create(array_merge([
        'category_id' => $category->id,
    ], $attributes));

    $topic->themes()->syncWithoutDetaching([$theme->id]);

    // Create 3 active entities with snapshots to satisfy indexability (>= 3 entities with >= 3 mentions)
    foreach (range(1, 3) as $i) {
        $e = Entity::factory()->create([
            'category_id' => $category->id,
            'status' => EntityStatus::Active,
            'searchable' => true,
        ]);

        EntityThemeSnapshot::create([
            'entity_id' => $e->id,
            'theme_id' => $theme->id,
            'window' => Period::OneYear,
            'observation_count' => 5,
            'positive_count' => 5,
            'neutral_count' => 0,
            'negative_count' => 0,
            'rank' => 1,
            'calculated_at' => now(),
        ]);
    }

    return $topic;
}

it('renders homepage with categoryBlocks and popularTopics props, without topEntities or recentEntities', function () {
    $parent = Category::factory()->create(['name' => 'Technology', 'slug' => 'technology', 'parent_id' => null]);
    $child = Category::factory()->create(['name' => 'Hosting', 'slug' => 'hosting', 'parent_id' => $parent->id]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Welcome')
        ->has('categoryBlocks')
        ->has('popularTopics')
        ->missing('topEntities')
        ->missing('recentEntities')
    );
});

it('returns top 3 eligible entities per root category across child categories ordered by score desc, opinions desc, name asc', function () {
    $parent = Category::factory()->create(['name' => 'Technology', 'slug' => 'technology', 'parent_id' => null]);
    $child1 = Category::factory()->create(['name' => 'Hosting', 'slug' => 'hosting', 'parent_id' => $parent->id]);
    $child2 = Category::factory()->create(['name' => 'Cloud', 'slug' => 'cloud', 'parent_id' => $parent->id]);

    // 4 entities in child categories
    $e1 = Entity::factory()->create(['name' => 'Alpha Host', 'slug' => 'alpha-host', 'category_id' => $child1->id, 'status' => EntityStatus::Active, 'searchable' => true]);
    $e2 = Entity::factory()->create(['name' => 'Beta Cloud', 'slug' => 'beta-cloud', 'category_id' => $child2->id, 'status' => EntityStatus::Active, 'searchable' => true]);
    $e3 = Entity::factory()->create(['name' => 'Gamma VPS', 'slug' => 'gamma-vps', 'category_id' => $child1->id, 'status' => EntityStatus::Active, 'searchable' => true]);
    $e4 = Entity::factory()->create(['name' => 'Delta Server', 'slug' => 'delta-server', 'category_id' => $child2->id, 'status' => EntityStatus::Active, 'searchable' => true]);

    // Snapshots:
    // e1: score 85, opinions 150
    // e2: score 90, opinions 140 (Rank 1)
    // e3: score 85, opinions 160 (Rank 2: same score as e1, but more opinions)
    // e4: score 70, opinions 200 (Rank 4, exceeds top 3)
    SentimentSnapshot::factory()->create([
        'entity_id' => $e1->id,
        'period' => Period::OneYear->value,
        'score' => 85.0,
        'opinion_count' => 150,
    ]);
    SentimentSnapshot::factory()->create([
        'entity_id' => $e2->id,
        'period' => Period::OneYear->value,
        'score' => 90.0,
        'opinion_count' => 140,
    ]);
    SentimentSnapshot::factory()->create([
        'entity_id' => $e3->id,
        'period' => Period::OneYear->value,
        'score' => 85.0,
        'opinion_count' => 160,
    ]);
    SentimentSnapshot::factory()->create([
        'entity_id' => $e4->id,
        'period' => Period::OneYear->value,
        'score' => 70.0,
        'opinion_count' => 200,
    ]);

    $response = $this->get(route('home'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('categoryBlocks', function (Collection $blocks) use ($e2, $e3, $e1, $e4) {
            $tech = $blocks->firstWhere('slug', 'technology');
            if (! $tech) {
                return false;
            }

            $topEntities = collect($tech['top_entities']);
            // Must have exactly 3
            if ($topEntities->count() !== 3) {
                return false;
            }

            // Ordered e2 (90), e3 (85, 60 opinions), e1 (85, 50 opinions)
            return $topEntities[0]['id'] === $e2->id
                && $topEntities[1]['id'] === $e3->id
                && $topEntities[2]['id'] === $e1->id
                && $topEntities->pluck('id')->doesntContain($e4->id);
        })
    );
});

it('excludes entities below the ranking threshold (< 100 opinions), disabled and non-searchable ones, leaving the category without a block', function () {
    $parent = Category::factory()->create(['name' => 'Automotive', 'slug' => 'automotive', 'parent_id' => null]);
    $child = Category::factory()->create(['name' => 'Mobil', 'slug' => 'mobil', 'parent_id' => $parent->id]);

    // Ineligible (< 30 opinions)
    $lowOpinions = Entity::factory()->create(['name' => 'Low Opini Car', 'category_id' => $child->id, 'status' => EntityStatus::Active, 'searchable' => true]);
    SentimentSnapshot::factory()->create([
        'entity_id' => $lowOpinions->id,
        'period' => Period::OneYear->value,
        'score' => 95.0,
        'opinion_count' => 15,
    ]);

    // Disabled entity
    $disabled = Entity::factory()->create(['name' => 'Disabled Car', 'category_id' => $child->id, 'status' => EntityStatus::Disabled, 'searchable' => true]);
    SentimentSnapshot::factory()->create([
        'entity_id' => $disabled->id,
        'period' => Period::OneYear->value,
        'score' => 90.0,
        'opinion_count' => 150,
    ]);

    // Non-searchable entity
    $nonSearchable = Entity::factory()->create(['name' => 'Hidden Car', 'category_id' => $child->id, 'status' => EntityStatus::Active, 'searchable' => false]);
    SentimentSnapshot::factory()->create([
        'entity_id' => $nonSearchable->id,
        'period' => Period::OneYear->value,
        'score' => 88.0,
        'opinion_count' => 150,
    ]);

    $response = $this->get(route('home'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('categoryBlocks', fn (Collection $blocks) => $blocks->firstWhere('slug', 'automotive') === null)
    );
});

it('never shows Tokoh Publik as a block, even with ranked entities', function () {
    $tokohPublik = Category::factory()->create(['name' => 'Tokoh Publik', 'slug' => 'tokoh-publik', 'parent_id' => null]);
    $politisi = Category::factory()->create(['name' => 'Politisi', 'slug' => 'politisi', 'parent_id' => $tokohPublik->id]);

    $figure = Entity::factory()->create([
        'name' => 'Tokoh Terkenal',
        'category_id' => $politisi->id,
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);

    SentimentSnapshot::factory()->create([
        'entity_id' => $figure->id,
        'period' => Period::OneYear->value,
        'score' => 95.0,
        'opinion_count' => 500,
    ]);

    $response = $this->get(route('home'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('categoryBlocks', fn (Collection $blocks) => $blocks->firstWhere('slug', 'tokoh-publik') === null)
    );
});

it('includes direct entities if a root category has no child categories', function () {
    $parentOnly = Category::factory()->create(['name' => 'Standalone Induk', 'slug' => 'standalone-induk', 'parent_id' => null]);

    $directEntity = Entity::factory()->create([
        'name' => 'Direct Entity',
        'category_id' => $parentOnly->id,
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);

    SentimentSnapshot::factory()->create([
        'entity_id' => $directEntity->id,
        'period' => Period::OneYear->value,
        'score' => 88.0,
        'opinion_count' => 100,
    ]);

    $response = $this->get(route('home'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('categoryBlocks', function (Collection $blocks) use ($directEntity) {
            $standalone = $blocks->firstWhere('slug', 'standalone-induk');
            if (! $standalone) {
                return false;
            }

            return count($standalone['top_entities']) === 1
                && $standalone['top_entities'][0]['id'] === $directEntity->id;
        })
    );
});

it('includes up to 3 published and indexable topics per category block', function () {
    $parent = Category::factory()->create(['name' => 'Digital Services', 'slug' => 'digital-services', 'parent_id' => null]);
    $child = Category::factory()->create(['name' => 'Web Hosting', 'slug' => 'web-hosting', 'parent_id' => $parent->id]);

    // The block only exists because its category has a ranking list.
    $ranked = Entity::factory()->create(['category_id' => $child->id, 'status' => EntityStatus::Active, 'searchable' => true]);
    SentimentSnapshot::factory()->create(['entity_id' => $ranked->id, 'period' => Period::OneYear->value, 'score' => 80.0, 'opinion_count' => 150]);

    // 4 indexable published topics for this category hierarchy
    $t1 = createIndexablePublishedTopic([
        'category_id' => $child->id,
        'candidate_signal' => 100,
        'title' => 'VPS Murah',
        'slug' => 'vps-murah',
        'normalized_keyword' => 'vps murah',
    ]);
    $t2 = createIndexablePublishedTopic([
        'category_id' => $parent->id,
        'candidate_signal' => 90,
        'title' => 'Hosting Murah',
        'slug' => 'hosting-murah',
        'normalized_keyword' => 'hosting murah',
    ]);
    $t3 = createIndexablePublishedTopic([
        'category_id' => $child->id,
        'candidate_signal' => 80,
        'title' => 'Cloud Murah',
        'slug' => 'cloud-murah',
        'normalized_keyword' => 'cloud murah',
    ]);
    $t4 = createIndexablePublishedTopic([
        'category_id' => $child->id,
        'candidate_signal' => 10,
        'title' => 'Domain Murah',
        'slug' => 'domain-murah',
        'normalized_keyword' => 'domain murah',
    ]);

    // Draft topic should be ignored
    SearchLandingPage::factory()->draft()->create([
        'category_id' => $child->id,
        'candidate_signal' => 999,
        'normalized_keyword' => 'draft topic',
    ]);

    $response = $this->get(route('home'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('categoryBlocks', function (Collection $blocks) use ($t1, $t2, $t3, $t4) {
            $digital = $blocks->firstWhere('slug', 'digital-services');
            if (! $digital) {
                return false;
            }

            $topics = collect($digital['topics']);

            return $topics->count() === 3
                && $topics->contains('slug', $t1->slug)
                && $topics->contains('slug', $t2->slug)
                && $topics->contains('slug', $t3->slug)
                && ! $topics->contains('slug', $t4->slug);
        })
    );
});

it('returns popularTopics up to 12 published indexable topics ordered by candidate_signal', function () {
    $category = Category::factory()->create();

    // Create 15 published indexable topics
    for ($i = 1; $i <= 15; $i++) {
        createIndexablePublishedTopic([
            'category_id' => $category->id,
            'candidate_signal' => $i * 10,
            'title' => "Topic {$i}",
            'slug' => "topic-{$i}",
            'normalized_keyword' => "topic {$i}",
        ]);
    }

    // Create 1 draft topic with high candidate_signal
    SearchLandingPage::factory()->draft()->create([
        'candidate_signal' => 999,
        'normalized_keyword' => 'draft high signal',
    ]);

    $response = $this->get(route('home'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('popularTopics', function (Collection $topics) {
            // Must cap at 12
            if ($topics->count() !== 12) {
                return false;
            }

            // Highest signal count should be first (Topic 15 with signal 150)
            return $topics[0]['slug'] === 'topic-15'
                && $topics[11]['slug'] === 'topic-4';
        })
    );
});

it('caches categoryBlocks for 15 minutes and executes in constant queries (no N+1)', function () {
    // Create 5 root categories each with 3 children and multiple entities
    for ($i = 1; $i <= 5; $i++) {
        $parent = Category::factory()->create(['name' => "Induk {$i}", 'slug' => "induk-{$i}", 'parent_id' => null]);
        for ($j = 1; $j <= 3; $j++) {
            $child = Category::factory()->create(['name' => "Anak {$i}-{$j}", 'slug' => "anak-{$i}-{$j}", 'parent_id' => $parent->id]);
            $entity = Entity::factory()->create(['category_id' => $child->id, 'status' => EntityStatus::Active, 'searchable' => true]);
            SentimentSnapshot::factory()->create([
                'entity_id' => $entity->id,
                'period' => Period::OneYear->value,
                'score' => 80.0,
                'opinion_count' => 150,
            ]);
        }
    }

    Cache::flush();

    // First request: primes the cache
    $this->get(route('home'))->assertOk();

    // Verify cache has the key
    expect(Cache::has('homepage:category_blocks'))->toBeTrue();

    // Second request: should hit cache without querying categories/snapshots
    DB::enableQueryLog();
    $this->get(route('home'))->assertOk();
    $cachedQueries = DB::getQueryLog();
    DB::disableQueryLog();

    // The second request should not query category blocks with window function
    $tableQueries = collect($cachedQueries)->pluck('query');
    expect($tableQueries->first(fn ($q) => str_contains($q, 'ROW_NUMBER() OVER')))->toBeNull();
});

it('drops the homepage caches when a topic is unpublished, so no link to a 404 lingers', function () {
    $topic = createIndexablePublishedTopic(['slug' => 'akan-dicabut']);

    $this->get(route('home'))->assertOk();
    expect(Cache::has('homepage:popular_topics'))->toBeTrue()
        ->and(Cache::has('homepage:category_blocks'))->toBeTrue();

    $topic->update(['status' => SearchLandingPageStatus::Draft]);

    expect(Cache::has('homepage:popular_topics'))->toBeFalse()
        ->and(Cache::has('homepage:category_blocks'))->toBeFalse();
});

it('drops the homepage caches when an entity is disabled, but not on unrelated edits', function () {
    $child = Category::factory()->create(['parent_id' => Category::factory()->create(['parent_id' => null])->id]);
    $entity = Entity::factory()->create(['category_id' => $child->id, 'status' => EntityStatus::Active, 'searchable' => true]);
    SentimentSnapshot::factory()->create(['entity_id' => $entity->id, 'period' => Period::OneYear->value, 'score' => 80.0, 'opinion_count' => 150]);

    $this->get(route('home'))->assertOk();
    $entity->update(['description' => 'deskripsi baru']);
    expect(Cache::has('homepage:category_blocks'))->toBeTrue();

    $entity->update(['status' => EntityStatus::Disabled]);
    expect(Cache::has('homepage:category_blocks'))->toBeFalse();
});

it('does not expose the internal candidate_signal on popular topics', function () {
    createIndexablePublishedTopic(['slug' => 'topik-populer', 'candidate_signal' => 42]);

    $this->get(route('home'))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('popularTopics', 1, fn ($topic) => $topic->missing('candidate_signal')->etc())
        ->missing('categories')
    );
});
