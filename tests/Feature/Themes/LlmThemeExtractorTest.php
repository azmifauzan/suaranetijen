<?php

use App\Domains\Entities\Models\Category;
use App\Domains\Entities\Models\Entity;
use App\Domains\Entities\Models\LlmSetting;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Sources\Models\Source;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Models\ThemeObservation;
use App\Domains\Themes\Services\LlmThemeExtractor;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function fakeThemeLlm(array $themes): void
{
    LlmSetting::create([
        'base_url' => 'https://llm.test/v1', 'model' => 'm', 'api_key' => 'k',
        'max_tokens' => 800, 'temperature' => 0.1, 'timeout_seconds' => 20,
    ]);
    Http::preventStrayRequests();
    Http::fake(['llm.test/*' => Http::response([
        'choices' => [['message' => ['content' => json_encode(['themes' => $themes])]]],
    ])]);
}

const OPINION = 'Samsung A55 kameranya bagus banget, tapi baterainya cepat habis sejak update kemarin.';

it('creates specific themes grounded in the opinion', function () {
    fakeThemeLlm([
        ['label' => 'kamera bagus', 'sentiment' => 'positive', 'evidence' => 'kameranya bagus banget', 'context' => 'Pengguna puas dengan hasil kamera A55.'],
        ['label' => 'baterai cepat habis', 'sentiment' => 'negative', 'evidence' => 'baterainya cepat habis', 'context' => 'Baterai terasa boros setelah pembaruan sistem.'],
    ]);

    $result = app(LlmThemeExtractor::class)->extract(1, 'Samsung', OPINION);

    expect($result)->toHaveCount(2)
        ->and($result[1]['theme']->display_label)->toBe('Baterai cepat habis')
        ->and($result[1]['sentiment'])->toBe(SentimentClass::Negative)
        ->and($result[1]['context'])->toBe('Baterai terasa boros setelah pembaruan sistem.')
        ->and(Theme::count())->toBe(2);
});

it('drops themes whose evidence is not in the opinion', function () {
    fakeThemeLlm([
        ['label' => 'layar retak', 'sentiment' => 'negative', 'evidence' => 'layarnya retak sendiri', 'context' => 'x'],
    ]);

    expect(app(LlmThemeExtractor::class)->extract(1, 'Samsung', OPINION))->toBe([])
        ->and(Theme::count())->toBe(0);
});

it('drops labels with handles or urls and nulls contexts that contain them', function () {
    fakeThemeLlm([
        ['label' => '@budi kamera', 'sentiment' => 'positive', 'evidence' => 'kameranya bagus', 'context' => 'ok'],
        ['label' => 'kamera bagus', 'sentiment' => 'positive', 'evidence' => 'kameranya bagus', 'context' => 'kata @budi di https://x.com'],
    ]);

    $result = app(LlmThemeExtractor::class)->extract(1, 'Samsung', OPINION);

    expect($result)->toHaveCount(1)
        ->and($result[0]['theme']->display_label)->toBe('Kamera bagus')
        ->and($result[0]['context'])->toBeNull();
});

it('reuses an existing theme instead of creating a duplicate', function () {
    $existing = Theme::create(['slug' => 'kamera-bagus', 'display_label' => 'Kamera bagus', 'canonical_key' => 'kamera-bagus']);
    fakeThemeLlm([
        ['label' => 'Kamera Bagus', 'sentiment' => 'positive', 'evidence' => 'kameranya bagus', 'context' => 'ok'],
    ]);

    $result = app(LlmThemeExtractor::class)->extract(1, 'Samsung', OPINION);

    expect($result[0]['theme']->id)->toBe($existing->id)
        ->and(Theme::count())->toBe(1);
});

it('truncates very long opinions before sending', function () {
    fakeThemeLlm([]);

    app(LlmThemeExtractor::class)->extract(1, 'Samsung', str_repeat('a ', 10_000));

    Http::assertSent(fn (Request $request) => mb_strlen($request['messages'][1]['content']) < 5_000);
});

it('queries known labels once and serves later calls from cache', function () {
    fakeThemeLlm([]);
    DB::enableQueryLog();

    app(LlmThemeExtractor::class)->extract(1, 'Samsung', OPINION);
    $first = count(DB::getQueryLog());
    app(LlmThemeExtractor::class)->extract(1, 'Samsung', OPINION);
    $second = count(DB::getQueryLog()) - $first;

    expect($second)->toBeLessThan($first);
});

it('groups per-opinion themes by opinion_index and keeps evidence grounded per opinion', function () {
    LlmSetting::create(['base_url' => 'https://llm.test/v1', 'model' => 'm', 'api_key' => 'k', 'max_tokens' => 800, 'temperature' => 0.1, 'timeout_seconds' => 20]);
    Http::preventStrayRequests();
    Http::fake(['llm.test/*' => Http::response([
        'choices' => [['message' => ['content' => json_encode([
            'results' => [
                ['opinion_index' => 1, 'themes' => [
                    ['label' => 'kamera bagus', 'sentiment' => 'positive', 'evidence' => 'kameranya bagus banget', 'context' => 'Pengguna puas dengan kamera.'],
                ]],
                ['opinion_index' => 2, 'themes' => [
                    ['label' => 'baterai cepat habis', 'sentiment' => 'negative', 'evidence' => 'baterai cepet abis', 'context' => 'Baterai dinilai boros.'],
                ]],
            ],
        ])]]],
    ])]);

    $result = app(LlmThemeExtractor::class)->extractBatch(1, 'Samsung', [
        'a' => ['key' => 'a', 'text' => 'Kameranya bagus banget buat foto malam.'],
        'b' => ['key' => 'b', 'text' => 'Sayangnya baterai cepet abis dipakai gaming.'],
    ]);

    expect($result['a'])->toHaveCount(1)
        ->and($result['a'][0]['theme']->display_label)->toBe('Kamera bagus')
        ->and($result['b'][0]['theme']->display_label)->toBe('Baterai cepat habis')
        ->and($result['b'][0]['sentiment'])->toBe(SentimentClass::Negative);
});

it('drops a batch theme whose evidence is not in ITS OWN opinion, even if it is in another', function () {
    Http::preventStrayRequests();
    LlmSetting::create(['base_url' => 'https://llm.test/v1', 'model' => 'm', 'api_key' => 'k', 'max_tokens' => 800, 'temperature' => 0.1, 'timeout_seconds' => 20]);
    Http::fake(['llm.test/*' => Http::response([
        'choices' => [['message' => ['content' => json_encode([
            'results' => [
                // Evidence belongs to opinion 2's text, wrongly attributed to opinion 1.
                ['opinion_index' => 1, 'themes' => [
                    ['label' => 'baterai cepat habis', 'sentiment' => 'negative', 'evidence' => 'baterai cepet abis', 'context' => 'x'],
                ]],
            ],
        ])]]],
    ])]);

    $result = app(LlmThemeExtractor::class)->extractBatch(1, 'Samsung', [
        ['key' => 0, 'text' => 'Kameranya bagus banget buat foto malam.'],
        ['key' => 1, 'text' => 'Sayangnya baterai cepet abis dipakai gaming.'],
    ]);

    expect($result)->toBe([]);
});

it('ignores an out-of-range opinion_index instead of crashing', function () {
    Http::preventStrayRequests();
    LlmSetting::create(['base_url' => 'https://llm.test/v1', 'model' => 'm', 'api_key' => 'k', 'max_tokens' => 800, 'temperature' => 0.1, 'timeout_seconds' => 20]);
    Http::fake(['llm.test/*' => Http::response([
        'choices' => [['message' => ['content' => json_encode([
            'results' => [['opinion_index' => 99, 'themes' => [['label' => 'x', 'sentiment' => 'positive', 'evidence' => 'x', 'context' => 'x']]]],
        ])]]],
    ])]);

    expect(app(LlmThemeExtractor::class)->extractBatch(1, 'Samsung', [['key' => 0, 'text' => 'Kameranya bagus.']]))->toBe([]);
});

it('never calls the LLM for an empty batch', function () {
    Http::preventStrayRequests();

    expect(app(LlmThemeExtractor::class)->extractBatch(1, 'Samsung', []))->toBe([]);
    Http::assertNothingSent();
});

it('only offers known labels from the same root category, never from unrelated ones', function () {
    $cars = Category::factory()->create();
    $food = Category::factory()->create();
    $car = Entity::factory()->create(['category_id' => $cars->id]);
    $milk = Entity::factory()->create(['category_id' => $food->id, 'name' => 'Greenfields']);
    $theme = Theme::create(['slug' => 'kualitas-mobil-bagus', 'display_label' => 'Kualitas mobil bagus', 'canonical_key' => 'kualitas-mobil-bagus']);
    ThemeObservation::create([
        'entity_id' => $car->id, 'theme_id' => $theme->id, 'source_id' => Source::factory()->create()->id,
        'sentiment' => SentimentClass::Positive, 'extractor' => 'llm',
    ]);
    fakeThemeLlm([]);

    app(LlmThemeExtractor::class)->extract($milk->id, 'Greenfields', 'Greenfields susunya enak banget.');

    Http::assertSent(fn (Request $request) => ! str_contains($request['messages'][1]['content'], 'Kualitas mobil bagus'));
});

it('reports opinions the model judges not to be about the entity and extracts no themes for them', function () {
    LlmSetting::create(['base_url' => 'https://llm.test/v1', 'model' => 'm', 'api_key' => 'k', 'max_tokens' => 800, 'temperature' => 0.1, 'timeout_seconds' => 20]);
    Http::preventStrayRequests();
    Http::fake(['llm.test/*' => Http::response([
        'choices' => [['message' => ['content' => json_encode([
            'results' => [
                ['opinion_index' => 1, 'about_entity' => true, 'themes' => [
                    ['label' => 'biaya admin murah', 'sentiment' => 'positive', 'evidence' => 'admin murah', 'context' => 'Biaya admin dinilai murah.'],
                ]],
                ['opinion_index' => 2, 'about_entity' => false, 'themes' => [
                    ['label' => 'kemasan praktis', 'sentiment' => 'positive', 'evidence' => 'tutup flip top praktis', 'context' => 'Tutup botol praktis.'],
                ]],
            ],
        ])]]],
    ])]);

    $offTopic = [];
    $result = app(LlmThemeExtractor::class)->extractBatch(1, 'Flip', [
        'a' => ['key' => 'a', 'text' => 'Transfer pakai Flip admin murah banget.'],
        'b' => ['key' => 'b', 'text' => 'Sampo ini tutup flip top praktis dan wangi.'],
    ], $offTopic);

    expect($offTopic)->toBe(['b'])
        ->and($result)->toHaveKey('a')
        ->and($result)->not->toHaveKey('b');
});

it('sends the entity profile so the model can tell the brand from a coincidental word', function () {
    $category = Category::factory()->create(['name' => 'Bank & E-Wallet']);
    $entity = Entity::factory()->create(['name' => 'Flip', 'category_id' => $category->id, 'description' => 'Aplikasi transfer uang antarbank']);
    fakeThemeLlm([]);

    app(LlmThemeExtractor::class)->extract($entity->id, 'Flip', OPINION);

    Http::assertSent(fn (Request $r) => str_contains($r->body(), 'Aplikasi transfer uang antarbank')
        && str_contains($r->body(), 'Bank & E-Wallet')
        && str_contains($r->body(), 'about_entity'));
});
