<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use Inertia\Testing\AssertableInertia;

test('a root category page lists entities, totals and topics of its child categories', function () {
    $root = Category::factory()->create(['slug' => 'induk-otomotif', 'parent_id' => null]);
    $child = Category::factory()->create(['parent_id' => $root->id]);
    $entity = Entity::factory()->create(['category_id' => $child->id, 'status' => EntityStatus::Active, 'searchable' => true, 'rankable' => true]);
    SentimentSnapshot::factory()->create(['entity_id' => $entity->id, 'period' => Period::OneYear->value, 'score' => 80.0, 'opinion_count' => 500]);
    SearchLandingPage::factory()->published()->create(['category_id' => $child->id, 'slug' => 'topik-anak']);

    $this->get('/category/induk-otomotif')->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Category/Show')
        ->where('category.total_entities', 1)
        ->has('mostDiscussed', 1)
        ->has('recentlyUpdated', 1)
        ->has('relatedTopics', 1)
    );
});

test('a root category without any entity is noindex and left out of the sitemap', function () {
    Category::factory()->create(['slug' => 'induk-kosong', 'parent_id' => null]);

    expect($this->get('/sitemap.xml')->getContent())->not->toContain('/category/induk-kosong');
});
