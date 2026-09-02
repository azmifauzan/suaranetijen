<?php

namespace App\Domains\Sentiment\Services;

use App\Domains\Entities\Services\TextNormalizer;
use App\Domains\Sentiment\Enums\SentimentClass;

class SentimentClassifier
{
    /**
     * @var list<string>
     */
    private const POSITIVE_TERMS = [
        'bagus', 'baik', 'puas', 'suka', 'mantap', 'keren', 'stabil', 'cepat', 'nyaman',
        'jernih', 'awet', 'lancar', 'murah', 'terjangkau', 'worth', 'recommended', 'rekomendasi',
        'oke', 'solid', 'ramah',
    ];

    /**
     * @var list<string>
     */
    private const NEGATIVE_TERMS = [
        'buruk', 'jelek', 'kecewa', 'lambat', 'mahal', 'lemot', 'gangguan', 'rusak', 'benci',
        'parah', 'tidak stabil', 'mengecewakan', 'zonk', 'error', 'down', 'scam', 'ribet',
    ];

    /**
     * @var list<string>
     */
    private const NEUTRAL_TERMS = ['lumayan', 'biasa', 'standar', 'cukup'];

    /**
     * @var list<string>
     */
    private const POSITIVE_EMOJI = ['😊', '😀', '😁', '👍', '❤️', '🔥', '💯', '😍', '🙏'];

    /**
     * @var list<string>
     */
    private const NEGATIVE_EMOJI = ['😡', '👎', '💔', '😞', '😠', '🤬', '😭'];

    public function classify(string $text): ?SentimentClass
    {
        // ponytail: lexical v1 keeps qualification deterministic; replace with evaluated NLP when live volume requires it.
        // Emoji are matched against the raw text first: normalization below strips them as symbols.
        $positive = 0;
        $negative = 0;

        foreach (self::POSITIVE_EMOJI as $emoji) {
            $positive += substr_count($text, $emoji);
        }

        foreach (self::NEGATIVE_EMOJI as $emoji) {
            $negative += substr_count($text, $emoji);
        }

        $normalizedText = TextNormalizer::normalize($text);
        if ($normalizedText === '' && $positive === 0 && $negative === 0) {
            return null;
        }

        $tokens = $normalizedText === '' ? [] : explode(' ', $normalizedText);
        $neutral = false;

        foreach ($tokens as $index => $token) {
            if (in_array($token, self::NEUTRAL_TERMS, true)) {
                $neutral = true;
            }

            if (in_array($token, self::POSITIVE_TERMS, true)) {
                if ($this->isNegated($tokens, $index)) {
                    $negative++;
                } else {
                    $positive++;
                }
            }

            if (in_array($token, self::NEGATIVE_TERMS, true)) {
                if ($this->isNegated($tokens, $index)) {
                    $positive++;
                } else {
                    $negative++;
                }
            }
        }

        if ($positive === 0 && $negative === 0 && ! $neutral) {
            return null;
        }

        if ($positive === $negative) {
            return SentimentClass::Neutral;
        }

        return $positive > $negative ? SentimentClass::Positive : SentimentClass::Negative;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function isNegated(array $tokens, int $index): bool
    {
        $negations = ['tidak', 'nggak', 'gak', 'ga', 'bukan', 'belum', 'kurang'];

        foreach (array_slice($tokens, max(0, $index - 3), 3) as $token) {
            if (in_array($token, $negations, true)) {
                return true;
            }
        }

        return false;
    }
}
