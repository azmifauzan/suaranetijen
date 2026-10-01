<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Sentiment\Services\SentimentRankingService;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Cache::flush();
});

/**
 * A root category with one child and $count entities that qualify for its /top ranking page.
 */
function rankedRoot(string $slug, int $count = 1, int $opinions = 150, bool $rankable = true): Category
{
    $root = Category::factory()->create(['name' => ucfirst($slug), 'slug' => $slug, 'parent_id' => null]);
    $child = Category::factory()->create(['parent_id' => $root->id]);

    foreach (range(1, $count) as $i) {
        $entity = Entity::factory()->create([
            'name' => "{$slug} entity {$i}",
            'category_id' => $child->id,
            'status' => EntityStatus::Active,
            'searchable' => true,
            'rankable' => $rankable,
        ]);
        SentimentSnapshot::factory()->create([
            'entity_id' => $entity->id,
            'period' => Period::OneYear->value,
            'score' => 90.0 - $i,
            'opinion_count' => $opinions,
        ]);
    }

    return $root;
}

function homeBlockSlugs($test): array
{
    $slugs = [];
    $test->get(route('home'))->assertInertia(function (AssertableInertia $page) use (&$slugs) {
        $slugs = collect($page->toArray()['props']['categoryBlocks'])->pluck('slug')->all();
    });

    return $slugs;
}

it('shows only categories whose /top ranking page has a list', function () {
    rankedRoot('punya-ranking');
    rankedRoot('terlalu-sedikit-opini', opinions: 50); // public score yes, ranking threshold (100) no
    rankedRoot('tidak-rankable', rankable: false);
    Category::factory()->create(['slug' => 'kosong', 'parent_id' => null]);

    expect(homeBlockSlugs($this))->toBe(['punya-ranking']);
});

it('only lists entities that really appear on the category ranking page', function () {
    rankedRoot('otomotif', count: 4);

    $this->get(route('home'))->assertInertia(function (AssertableInertia $page) {
        $block = collect($page->toArray()['props']['categoryBlocks'])->firstWhere('slug', 'otomotif');
        $onHome = collect($block['top_entities'])->pluck('id')->all();
        $ranking = collect(app(SentimentRankingService::class)
            ->getRanking(Category::where('slug', 'otomotif')->value('id')))
            ->pluck('entity.id')->take(3)->all();

        expect($onHome)->toBe($ranking);
    });
});

it('caps the homepage at six blocks, richest ranking first', function () {
    foreach (range(1, 8) as $n) {
        rankedRoot("kategori-{$n}", count: $n);
    }

    expect(homeBlockSlugs($this))->toBe([
        'kategori-8', 'kategori-7', 'kategori-6', 'kategori-5', 'kategori-4', 'kategori-3',
    ]);
});

it('trims to a full row of three when only four or five categories qualify', function () {
    foreach (range(1, 5) as $n) {
        rankedRoot("kategori-{$n}", count: $n);
    }

    expect(homeBlockSlugs($this))->toHaveCount(3);
});

it('never shows Tokoh Publik as a block', function () {
    $root = rankedRoot('tokoh-publik');
    expect($root->slug)->toBe('tokoh-publik');

    expect(homeBlockSlugs($this))->toBe([]);
});
