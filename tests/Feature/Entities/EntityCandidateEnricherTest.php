<?php

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\LlmSetting;
use App\Domains\Entities\Services\EntityCandidateEnricher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('enriches a candidate term and resolves the suggested category to an existing category id', function () {
    LlmSetting::create(['base_url' => 'https://llm.internal/v1', 'model' => 'test-model', 'api_key' => 'key']);
    $category = Category::query()->create(['name' => 'Smartphone', 'slug' => 'smartphone']);

    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        expect($request['messages'][1]['content'])->toContain('iphone 17 pro')
            ->and($request['response_format']['type'])->toBe('json_schema');

        return Http::response(['choices' => [['message' => ['content' => json_encode([
            'is_relevant' => true,
            'suggested_name' => 'iPhone 17 Pro',
            'suggested_entity_type' => 'product',
            'suggested_category' => 'Smartphone',
            'suggested_aliases' => ['iphone 17 pro', 'ip17 pro'],
            'reasoning' => 'Frequently searched new Apple smartphone model.',
        ])]]]]);
    });

    $result = app(EntityCandidateEnricher::class)->enrich('iphone 17 pro', ['iphone 17 pro', 'ip17 pro']);

    expect($result['suggested_name'])->toBe('iPhone 17 Pro')
        ->and($result['suggested_entity_type'])->toBe('product')
        ->and($result['suggested_category_id'])->toBe($category->id)
        ->and($result['suggested_aliases'])->toBe(['iphone 17 pro', 'ip17 pro'])
        ->and($result['reasoning'])->toBe('Frequently searched new Apple smartphone model.');
});

it('leaves suggested_category_id null when the LLM suggests a category that does not exist', function () {
    LlmSetting::create(['base_url' => 'https://llm.internal/v1', 'model' => 'test-model', 'api_key' => 'key']);

    Http::preventStrayRequests();
    Http::fake(fn () => Http::response(['choices' => [['message' => ['content' => json_encode([
        'is_relevant' => true,
        'suggested_name' => 'Something',
        'suggested_entity_type' => 'brand',
        'suggested_category' => 'Nonexistent Category',
        'suggested_aliases' => [],
        'reasoning' => 'n/a',
    ])]]]]));

    $result = app(EntityCandidateEnricher::class)->enrich('something', ['something']);

    expect($result['suggested_category_id'])->toBeNull();
});

it('discards an entity_type outside the allowed brand/product/service set', function () {
    LlmSetting::create(['base_url' => 'https://llm.internal/v1', 'model' => 'test-model', 'api_key' => 'key']);

    Http::preventStrayRequests();
    Http::fake(fn () => Http::response(['choices' => [['message' => ['content' => json_encode([
        'is_relevant' => true,
        'suggested_name' => 'Something',
        'suggested_entity_type' => 'not-a-real-type',
        'suggested_category' => 'Smartphone',
        'suggested_aliases' => [],
        'reasoning' => 'n/a',
    ])]]]]));

    $result = app(EntityCandidateEnricher::class)->enrich('something', ['something']);

    expect($result['suggested_entity_type'])->toBeNull();
});

it('clears every suggested field when the LLM judges the term is not relevant', function () {
    LlmSetting::create(['base_url' => 'https://llm.internal/v1', 'model' => 'test-model', 'api_key' => 'key']);

    Http::preventStrayRequests();
    Http::fake(fn () => Http::response(['choices' => [['message' => ['content' => json_encode([
        'is_relevant' => false,
        'suggested_name' => 'Man City vs Coventry City',
        'suggested_entity_type' => 'brand',
        'suggested_category' => 'Brand Umum',
        'suggested_aliases' => ['MCFC vs Coventry'],
        'reasoning' => 'This is a football match result, not a brand/product/service.',
    ])]]]]));

    $result = app(EntityCandidateEnricher::class)->enrich('man city vs coventry city', ['man city vs coventry city']);

    expect($result['is_relevant'])->toBeFalse()
        ->and($result['suggested_name'])->toBeNull()
        ->and($result['suggested_entity_type'])->toBeNull()
        ->and($result['suggested_category_id'])->toBeNull()
        ->and($result['suggested_aliases'])->toBe([])
        ->and($result['reasoning'])->toBe('This is a football match result, not a brand/product/service.');
});
