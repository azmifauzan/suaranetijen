<?php

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use Inertia\Testing\AssertableInertia;

it('lists a ranked parent taxonomy category as one block with its children nested, not as separate blocks', function () {
    $parent = Category::factory()->create(['name' => 'Automotive', 'parent_id' => null]);
    $child = Category::factory()->create(['name' => 'Motor', 'parent_id' => $parent->id]);
    $entity = Entity::factory()->create(['category_id' => $child->id]);
    SentimentSnapshot::factory()->create(['entity_id' => $entity->id, 'period' => Period::OneYear->value, 'score' => 80.0, 'opinion_count' => 150]);

    $response = $this->get(route('home'));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('categoryBlocks', function ($blocks) use ($parent) {
            $blocks = collect($blocks);
            $block = $blocks->firstWhere('slug', $parent->slug);

            return $block !== null
                && $blocks->pluck('name')->doesntContain('Motor')
                && collect($block['child_categories'])->pluck('name')->contains('Motor');
        })
    );
});
