<?php

namespace App\Domains\Sentiment\Enums;

enum SentimentClass: string
{
    case Positive = 'positive';
    case Neutral = 'neutral';
    case Negative = 'negative';

    /**
     * Score weight in the v1 scoring formula (positive=100, neutral=50, negative=0).
     */
    public function weight(): int
    {
        return match ($this) {
            self::Positive => 100,
            self::Neutral => 50,
            self::Negative => 0,
        };
    }
}
