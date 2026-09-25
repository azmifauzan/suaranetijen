<?php

use App\Domains\Entities\Models\LlmSetting;
use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Themes\Models\Theme;
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
