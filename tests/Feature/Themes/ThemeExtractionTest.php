<?php

use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Themes\Services\ThemeExtractor;
use App\Domains\Themes\Services\ThemeNormalizer;

test('ThemeNormalizer seeds canonical default themes and aliases per docs/25', function () {
    $normalizer = new ThemeNormalizer;
    $themes = $normalizer->seedDefaultThemes();

    expect($themes)->toHaveKey('speed_fast')
        ->and($themes)->toHaveKey('speed_slow')
        ->and($themes)->toHaveKey('price_affordable')
        ->and($themes)->toHaveKey('support_slow');

    $resolvedCepat = $normalizer->resolveTheme('ngebut');
    expect($resolvedCepat)->not->toBeNull()
        ->and($resolvedCepat->canonical_key)->toBe('speed_fast');

    $resolvedMurah = $normalizer->resolveTheme('ramah kantong');
    expect($resolvedMurah)->not->toBeNull()
        ->and($resolvedMurah->canonical_key)->toBe('price_affordable');
});

test('ThemeExtractor extracts multiple themes and sentiments from opinion text per docs/25 worked example', function () {
    $normalizer = new ThemeNormalizer;
    $normalizer->seedDefaultThemes();

    $extractor = new ThemeExtractor($normalizer);

    // docs/25 line 55 worked example:
    // "Servernya ngebut, tapi support ticket saya kemarin lama dibalas."
    // Expected: cepat -> positive, support lambat -> negative
    $text = 'Servernya ngebut, tapi support ticket saya kemarin lama dibalas. Slow respon banget cs nya.';
    $extracted = $extractor->extract($text);

    expect($extracted)->toHaveCount(2);

    $keys = array_map(fn ($item) => $item['theme']->canonical_key, $extracted);
    expect($keys)->toContain('speed_fast')
        ->and($keys)->toContain('support_slow');

    $fastItem = collect($extracted)->firstWhere('theme.canonical_key', 'speed_fast');
    $slowItem = collect($extracted)->firstWhere('theme.canonical_key', 'support_slow');

    expect($fastItem['sentiment'])->toBe(SentimentClass::Positive)
        ->and($slowItem['sentiment'])->toBe(SentimentClass::Negative);
});

test('ThemeExtractor collapses multiple synonyms in the same text into one theme observation', function () {
    $normalizer = new ThemeNormalizer;
    $normalizer->seedDefaultThemes();

    $extractor = new ThemeExtractor($normalizer);

    // Both 'murah', 'terjangkau', and 'ramah kantong' map to price_affordable
    $text = 'Hosting ini sangat murah, harganya terjangkau dan ramah kantong!';
    $extracted = $extractor->extract($text);

    expect($extracted)->toHaveCount(1)
        ->and($extracted[0]['theme']->canonical_key)->toBe('price_affordable');
});
