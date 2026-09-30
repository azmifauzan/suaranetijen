<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Themes\Models\EntityThemeSnapshot;
use App\Domains\Themes\Models\Theme;

test('it returns 404 for candidate, draft, or rejected topic pages', function () {
    $candidate = SearchLandingPage::factory()->candidate()->create(['slug' => 'topik-candidate']);
    $draft = SearchLandingPage::factory()->draft()->create(['slug' => 'topik-draft']);
    $rejected = SearchLandingPage::factory()->rejected()->create(['slug' => 'topik-rejected']);

    $this->get('/topik/topik-candidate')->assertNotFound();
    $this->get('/topik/topik-draft')->assertNotFound();
    $this->get('/topik/topik-rejected')->assertNotFound();
});

test('it returns 200 for published topic page', function () {
    $category = Category::factory()->create();
    $topic = SearchLandingPage::factory()->published()->create([
        'category_id' => $category->id,
        'slug' => 'vps-murah',
        'title' => 'VPS Murah untuk Pemula',
    ]);

    $response = $this->get('/topik/vps-murah');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Topics/Show')
        ->has('topic')
        ->where('topic.slug', 'vps-murah')
        ->where('topic.title', 'VPS Murah untuk Pemula')
        ->has('entities')
    );
});

test('it does not contain AggregateRating in rendered page or json-ld', function () {
    $category = Category::factory()->create();
    $topic = SearchLandingPage::factory()->published()->create([
        'category_id' => $category->id,
        'slug' => 'hosting-murah',
    ]);

    $response = $this->get('/topik/hosting-murah');

    $response->assertOk();
    $response->assertDontSee('AggregateRating');
});

test('sitemap includes /topik and only indexable published topics', function () {
    $category = Category::factory()->create();
    $theme = Theme::create([
        'slug' => 'awet',
        'display_label' => 'Awet',
        'canonical_key' => 'awet',
    ]);

    // Topic 1: published and indexable (>= 3 entities with >= 3 mentions)
    $indexableTopic = SearchLandingPage::factory()->published()->create([
        'category_id' => $category->id,
        'slug' => 'hp-baterai-awet',
    ]);
    $indexableTopic->themes()->attach($theme->id);

    foreach (range(1, 3) as $i) {
        $e = Entity::factory()->create(['category_id' => $category->id, 'status' => EntityStatus::Active, 'searchable' => true]);
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

    // Topic 2: published but NOT indexable (0 entities in category)
    $emptyCategory = Category::factory()->create();
    $nonIndexableTopic = SearchLandingPage::factory()->published()->create([
        'category_id' => $emptyCategory->id,
        'slug' => 'topik-sepi',
    ]);
    $nonIndexableTopic->themes()->attach($theme->id);

    // Topic 3: draft topic
    $draftTopic = SearchLandingPage::factory()->draft()->create([
        'category_id' => $category->id,
        'slug' => 'topik-draft-sitemap',
    ]);

    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $content = $response->getContent();

    expect($content)->toContain('/topik')
        ->and($content)->toContain('/topik/hp-baterai-awet')
        ->and($content)->not->toContain('/topik/topik-sepi')
        ->and($content)->not->toContain('/topik/topik-draft-sitemap');
});

test('hub /topik renders only indexable published topics', function () {
    $category = Category::factory()->create(['name' => 'Hosting']);
    $theme = Theme::create([
        'slug' => 'cepat',
        'display_label' => 'Cepat',
        'canonical_key' => 'cepat',
    ]);

    $topic = SearchLandingPage::factory()->published()->create([
        'category_id' => $category->id,
        'slug' => 'vps-cepat',
        'title' => 'VPS Cepat Indonesia',
    ]);
    $topic->themes()->attach($theme->id);

    foreach (range(1, 3) as $i) {
        $e = Entity::factory()->create(['category_id' => $category->id, 'status' => EntityStatus::Active, 'searchable' => true]);
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

    $response = $this->get('/topik');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Topics/Index')
        ->has('groupedTopics')
        ->where('totalTopics', 1)
    );
});

test('sitemap omits the empty /topik hub when no topic is indexable', function () {
    $content = $this->get('/sitemap.xml')->getContent();

    expect($content)->not->toContain('/topik');
});

test('empty hub /topik is noindex', function () {
    $this->get('/topik')->assertInertia(fn ($page) => $page
        ->component('Topics/Index')
        ->where('totalTopics', 0)
        ->where('isIndexable', false)
    );
});
