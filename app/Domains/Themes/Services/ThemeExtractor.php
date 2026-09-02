<?php

namespace App\Domains\Themes\Services;

use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Models\ThemeAlias;

class ThemeExtractor
{
    public function __construct(
        public ThemeNormalizer $normalizer = new ThemeNormalizer
    ) {}

    /**
     * Extract theme candidates and inferred sentiment from text.
     *
     * @return array<int, array{theme: Theme, sentiment: SentimentClass, confidence: float}>
     */
    public function extract(string $text, ?SentimentClass $fallbackSentiment = null): array
    {
        $normalizedText = ' '.$this->normalizer->normalize($text).' ';
        if (trim($normalizedText) === '') {
            return [];
        }

        // Fetch all aliases sorted by length descending so longer phrases match first
        $aliases = ThemeAlias::query()
            ->with('theme')
            ->get()
            ->sortByDesc(fn (ThemeAlias $a) => mb_strlen($a->normalized_alias));

        $foundThemes = [];

        foreach ($aliases as $alias) {
            $pattern = ' '.$alias->normalized_alias.' ';
            if (str_contains($normalizedText, $pattern)) {
                $themeId = $alias->theme_id;

                if (! isset($foundThemes[$themeId])) {
                    $sentiment = $this->determineSentimentForTheme($alias->theme, $fallbackSentiment);

                    $foundThemes[$themeId] = [
                        'theme' => $alias->theme,
                        'sentiment' => $sentiment,
                        'confidence' => 0.90,
                    ];
                }
            }
        }

        return array_values($foundThemes);
    }

    /**
     * Inferred sentiment based on the theme's nature or fallback.
     */
    protected function determineSentimentForTheme(Theme $theme, ?SentimentClass $fallback): SentimentClass
    {
        // Check if theme canonical key reflects inherently positive or negative meaning
        if (str_contains($theme->canonical_key, '_good')
            || str_contains($theme->canonical_key, '_fast')
            || str_contains($theme->canonical_key, '_affordable')
            || str_contains($theme->canonical_key, '_reliable')) {
            return SentimentClass::Positive;
        }

        if (str_contains($theme->canonical_key, '_poor')
            || str_contains($theme->canonical_key, '_slow')
            || str_contains($theme->canonical_key, '_expensive')
            || str_contains($theme->canonical_key, '_unreliable')) {
            return SentimentClass::Negative;
        }

        return $fallback ?? SentimentClass::Neutral;
    }
}
