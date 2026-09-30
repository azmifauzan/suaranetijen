<?php

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\LlmSetting;
use App\Domains\Search\Enums\SearchLandingPageStatus;
use App\Domains\Search\Models\SearchLandingPage;
use App\Domains\Search\Services\TopicDraftWriter;
use App\Domains\Themes\Models\Theme;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    LlmSetting::create([
        'base_url' => 'https://llm.test/v1',
        'model' => 'gpt-4o',
        'api_key' => 'test-key',
        'max_tokens' => 800,
        'temperature' => 0.2,
        'timeout_seconds' => 30,
    ]);
});

test('it marks topic as rejected when LLM returns is_relevant false', function () {
    Http::fake([
        'llm.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'is_relevant' => false,
                        'category_id' => 1,
                        'theme_ids' => [1],
                        'title' => 'Judul Tidak Relevan',
                        'meta_description' => 'Deskripsi tidak relevan',
                        'intro' => 'Intro',
                    ]),
                ],
            ]],
        ]),
    ]);

    $category = Category::factory()->create();
    $theme = Theme::create(['slug' => 'test', 'display_label' => 'Test', 'canonical_key' => 'test']);
    $topic = SearchLandingPage::factory()->candidate()->create(['keyword' => 'slot gacor']);

    $writer = app(TopicDraftWriter::class);
    $status = $writer->draft($topic);

    expect($status)->toBe(SearchLandingPageStatus::Rejected);
    expect($topic->fresh()->status)->toBe(SearchLandingPageStatus::Rejected);
});

test('it rejects drafts with category_id outside valid categories', function () {
    $category = Category::factory()->create();
    $theme = Theme::create(['slug' => 'murah', 'display_label' => 'Murah', 'canonical_key' => 'murah']);
    $topic = SearchLandingPage::factory()->candidate()->create(['keyword' => 'vps murah']);

    Http::fake([
        'llm.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'is_relevant' => true,
                        'category_id' => 99999, // Outside input
                        'theme_ids' => [$theme->id],
                        'title' => 'Pilihan VPS Murah',
                        'meta_description' => 'Rangkuman opini netizen tentang vps murah.',
                        'intro' => 'Netizen sering membicarakan opsi vps murah untuk server.',
                    ]),
                ],
            ]],
        ]),
    ]);

    $writer = app(TopicDraftWriter::class);
    $status = $writer->draft($topic);

    expect($status)->toBe(SearchLandingPageStatus::Candidate);
    expect($topic->fresh()->status)->toBe(SearchLandingPageStatus::Candidate);
});

test('it rejects drafts violating public copy guard', function () {
    $category = Category::factory()->create();
    $theme = Theme::create(['slug' => 'murah', 'display_label' => 'Murah', 'canonical_key' => 'murah']);
    $topic = SearchLandingPage::factory()->candidate()->create(['keyword' => 'vps murah']);

    Http::fake([
        'llm.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'is_relevant' => true,
                        'category_id' => $category->id,
                        'theme_ids' => [$theme->id],
                        'title' => 'VPS Terbaik di Indonesia', // Violates guard (terbaik)
                        'meta_description' => 'Diskon 50% untuk hosting murah.', // Violates guard (%)
                        'intro' => 'Hubungi @admin di https://example.com.', // Violates guard (@ and url)
                    ]),
                ],
            ]],
        ]),
    ]);

    $writer = app(TopicDraftWriter::class);
    $status = $writer->draft($topic);

    expect($status)->toBe(SearchLandingPageStatus::Candidate);
    expect($topic->fresh()->status)->toBe(SearchLandingPageStatus::Candidate);
});

test('it keeps candidate status when LLM returns error', function () {
    $category = Category::factory()->create();
    $topic = SearchLandingPage::factory()->candidate()->create(['keyword' => 'vps murah']);

    Http::fake([
        'llm.test/*' => Http::response([], 500),
    ]);

    $writer = app(TopicDraftWriter::class);
    $status = $writer->draft($topic);

    expect($status)->toBe(SearchLandingPageStatus::Candidate);
    expect($topic->fresh()->status)->toBe(SearchLandingPageStatus::Candidate);
});

test('it saves valid draft and attaches themes', function () {
    $category = Category::factory()->create();
    $theme = Theme::create(['slug' => 'stabil', 'display_label' => 'Stabil', 'canonical_key' => 'stabil']);
    $topic = SearchLandingPage::factory()->candidate()->create(['keyword' => 'hosting stabil']);

    Http::fake([
        'llm.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'is_relevant' => true,
                        'category_id' => $category->id,
                        'theme_ids' => [$theme->id],
                        'title' => 'Hosting Stabil Pilihan Netizen',
                        'meta_description' => 'Kumpulan hosting yang dinilai stabil oleh pengguna di Indonesia.',
                        'intro' => 'Kestabilan server menjadi faktor penting dalam memilih hosting. Netizen banyak berbagi pengalaman mengenai uptime dan keandalan.',
                    ]),
                ],
            ]],
        ]),
    ]);

    $writer = app(TopicDraftWriter::class);
    $status = $writer->draft($topic);

    expect($status)->toBe(SearchLandingPageStatus::Draft);

    $fresh = $topic->fresh();
    expect($fresh->status)->toBe(SearchLandingPageStatus::Draft)
        ->and($fresh->category_id)->toBe($category->id)
        ->and($fresh->title)->toBe('Hosting Stabil Pilihan Netizen')
        ->and($fresh->themes->pluck('id')->all())->toBe([$theme->id])
        ->and($fresh->llm_drafted_at)->not->toBeNull();
});

test('it never touches a published topic', function () {
    Http::fake();

    $category = Category::factory()->create();
    $topic = SearchLandingPage::factory()->published()->create(['category_id' => $category->id, 'title' => 'Judul Lama']);

    $status = app(TopicDraftWriter::class)->draft($topic);

    expect($status)->toBe(SearchLandingPageStatus::Published)
        ->and($topic->fresh()->title)->toBe('Judul Lama');
    Http::assertNothingSent();
});

test('preset themes of a category-theme candidate are always offered to the LLM and kept when it picks them', function () {
    $category = Category::factory()->create();
    foreach (range(1, 35) as $i) {
        Theme::create(['slug' => "vps-noise-{$i}", 'display_label' => "vps noise {$i}", 'canonical_key' => "vps-noise-{$i}"]);
    }
    $preset = Theme::create(['slug' => 'harga-terjangkau', 'display_label' => 'harga terjangkau', 'canonical_key' => 'harga-terjangkau']);
    $topic = SearchLandingPage::factory()->candidate()->create(['keyword' => 'vps harga terjangkau', 'category_id' => $category->id]);
    $topic->themes()->attach($preset->id);

    Http::fake([
        'llm.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'is_relevant' => true,
            'category_id' => $category->id,
            'theme_ids' => [$preset->id],
            'title' => 'VPS Harga Terjangkau',
            'meta_description' => 'Netizen membahas VPS dengan harga terjangkau.',
            'intro' => 'Netizen sering membahas harga VPS.',
        ])]]]]),
    ]);

    app(TopicDraftWriter::class)->draft($topic);

    Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), "[id: {$preset->id}] harga terjangkau"));
    expect($topic->fresh()->themes->pluck('id')->all())->toBe([$preset->id]);
});
