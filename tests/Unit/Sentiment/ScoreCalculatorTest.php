<?php

use App\Domains\Sentiment\Services\ScoreCalculator;

test('docs/11 worked example: 60 positive, 20 neutral, 20 negative yields exactly 70.0', function () {
    // docs/11: score = 100 * (P + 0.5 * N) / T
    // 100 * (60 + 0.5 * 20) / 100 = 70.0
    $score = ScoreCalculator::calculate(positive: 60, neutral: 20, negative: 20);

    expect($score)->toBe(70.0);
});

test('score calculation handles all positive, all neutral, all negative', function () {
    expect(ScoreCalculator::calculate(100, 0, 0))->toBe(100.0)
        ->and(ScoreCalculator::calculate(0, 100, 0))->toBe(50.0)
        ->and(ScoreCalculator::calculate(0, 0, 100))->toBe(0.0);
});

test('score calculation returns null when zero opinions exist', function () {
    expect(ScoreCalculator::calculate(0, 0, 0))->toBeNull();
});

test('public score threshold eligibility requires at least 30 opinions', function () {
    expect(ScoreCalculator::isPublicScoreEligible(29))->toBeFalse()
        ->and(ScoreCalculator::isPublicScoreEligible(30))->toBeTrue()
        ->and(ScoreCalculator::isPublicScoreEligible(100))->toBeTrue();
});

test('ranking eligibility requires at least 100 opinions', function () {
    expect(ScoreCalculator::isRankingEligible(99))->toBeFalse()
        ->and(ScoreCalculator::isRankingEligible(100))->toBeTrue()
        ->and(ScoreCalculator::isRankingEligible(250))->toBeTrue();
});

test('thresholds and formula version are read from config/scoring.php, not hard-coded', function () {
    config([
        'scoring.public_min_opinions' => 5,
        'scoring.ranking_min_opinions' => 10,
        'scoring.formula_version' => 'v2-test',
    ]);

    expect(ScoreCalculator::isPublicScoreEligible(5))->toBeTrue()
        ->and(ScoreCalculator::isPublicScoreEligible(4))->toBeFalse()
        ->and(ScoreCalculator::isRankingEligible(10))->toBeTrue()
        ->and(ScoreCalculator::isRankingEligible(9))->toBeFalse()
        ->and(ScoreCalculator::formulaVersion())->toBe('v2-test');
});
