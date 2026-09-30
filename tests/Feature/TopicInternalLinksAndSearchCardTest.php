<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\Theme;

test('category show and top show include published related topics up to 6', function () {
    $category = Category::factory()->create(['slug' => 'cloud-hosting']);
    $otherCategory = Category::factory()->create();

    // Create 7 published topics for category (should limit to 6)
    foreach (range(1, 7) as $i) {
        SearchLandingPage::factory()->published()->create([
            'category_id' => $category->id,
            'keyword' => "Topik {$i}",
            'slug' => "topik-{$i}",
        ]);
    }

    // 1 draft topic (should be excluded)
    SearchLandingPage::factory()->draft()->create([
        'category_id' => $category->id,
        'keyword' => 'Topik Draft',
        'slug' => 'topik-draft',
    ]);

    // 1 topic in another category (should be excluded)
    SearchLandingPage::factory()->published()->create([
        'category_id' => $otherCategory->id,
        'keyword' => 'Topik Lain',
        'slug' => 'topik-lain',
    ]);

    // 1. /category/{slug}
    $resCat = $this->get('/category/cloud-hosting');
    $resCat->assertOk();
    $resCat->assertInertia(fn ($page) => $page
        ->component('Category/Show')
        ->has('relatedTopics', 6)
    );

    // 2. /top/{slug}
    $resTop = $this->get('/top/cloud-hosting');
    $resTop->assertOk();
    $resTop->assertInertia(fn ($page) => $page
        ->component('Top/Show')
        ->has('relatedTopics', 6)
    );
});

test('entity show includes published topics that feature this entity up to 4', function () {
    $category = Category::factory()->create();
    $entity = Entity::factory()->create([
        'category_id' => $category->id,
        'status' => EntityStatus::Active,
        'searchable' => true,
    ]);

    $theme = Theme::create(['slug' => 'murah', 'display_label' => 'Murah', 'canonical_key' => 'murah']);
    EntityThemeSnapshot::create([
        'entity_id' => $entity->id,
        'theme_id' => $theme->id,
        'window' => Period::OneYear,
        'observation_count' => 10,
        'positive_count' => 8,
        'neutral_count' => 2,
        'negative_count' => 0,
        'rank' => 1,
        'calculated_at' => now(),
    ]);

    // 5 published topics containing this category and theme (limit 4)
    foreach (range(1, 5) as $i) {
        $topic = SearchLandingPage::factory()->published()->create([
            'category_id' => $category->id,
            'keyword' => "Topik Entity {$i}",
            'slug' => "topik-entity-{$i}",
        ]);
        $topic->themes()->attach($theme->id);
    }

    $response = $this->get("/e/{$entity->slug}");
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Entities/Show')
        ->has('includedTopics', 4)
    );
});

test('search results display topic card only on exact normalized match with published topic', function () {
    $published = SearchLandingPage::factory()->published()->create([
        'keyword' => 'VPS Murah',
        'normalized_keyword' => 'vps murah',
        'title' => 'Rekomendasi VPS Murah',
        'slug' => 'vps-murah',
    ]);

    $draft = SearchLandingPage::factory()->draft()->create([
        'keyword' => 'Hosting Cepat',
        'normalized_keyword' => 'hosting cepat',
        'slug' => 'hosting-cepat',
    ]);

    // 1. Exact match with published topic (with varying case and punctuation)
    $res1 = $this->get('/search?q=VPS+Murah!');
    $res1->assertOk();
    $res1->assertInertia(fn ($page) => $page
        ->component('Search/Index')
        ->has('matchingTopic')
        ->where('matchingTopic.slug', 'vps-murah')
        ->where('matchingTopic.title', 'Rekomendasi VPS Murah')
    );

    // 2. Partial match does not show card
    $res2 = $this->get('/search?q=vps');
    $res2->assertOk();
    $res2->assertInertia(fn ($page) => $page
        ->component('Search/Index')
        ->where('matchingTopic', null)
    );

    // 3. Draft topic match does not show card
    $res3 = $this->get('/search?q=hosting+cepat');
    $res3->assertOk();
    $res3->assertInertia(fn ($page) => $page
        ->component('Search/Index')
        ->where('matchingTopic', null)
    );
});
