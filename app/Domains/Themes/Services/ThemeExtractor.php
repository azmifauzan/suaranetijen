<?php

namespace App\Domains\Themes\Services;

use App\Domains\Sentiment\Enums\SentimentClass;
use App\Domains\Themes\Models\Theme;
use App\Domains\Themes\Models\ThemeAlias;

class ThemeExtractor
{
    /**
     * Indonesian negation markers checked in a small window before a matched alias.
     * A theme whose polarity is derived from its canonical key ("cepat" -> positive)
     * flips when the mention is actually negated ("gak cepat" -> negative), so a
     * negated mention doesn't get stamped with the theme's default polarity.
     *
     * @var array<int, string>
     */
    private const NEGATION_MARKERS = ['tidak', 'nggak', 'ga', 'gak', 'enggak', 'bukan', 'kurang', 'belum', 'tak'];

    private const NEGATION_WINDOW = 3;

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
            $position = strpos($normalizedText, $pattern);

            if ($position !== false) {
                $themeId = $alias->theme_id;

                if (! isset($foundThemes[$themeId])) {
                    [$sentiment, $nameDerived] = $this->determineSentimentForTheme($alias->theme, $fallbackSentiment);

                    if ($nameDerived && $this->isNegated($normalizedText, $position)) {
                        $sentiment = $this->flip($sentiment);
                    }

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
     *
     * @return array{0: SentimentClass, 1: bool} sentiment and whether it was derived from the theme's name (vs. the fallback)
     */
    protected function determineSentimentForTheme(Theme $theme, ?SentimentClass $fallback): array
    {
        // Check if theme canonical key reflects inherently positive or negative meaning
        if (str_contains($theme->canonical_key, '_good')
            || str_contains($theme->canonical_key, '_fast')
            || str_contains($theme->canonical_key, '_affordable')
            || str_contains($theme->canonical_key, '_reliable')) {
            return [SentimentClass::Positive, true];
        }

        if (str_contains($theme->canonical_key, '_poor')
            || str_contains($theme->canonical_key, '_slow')
            || str_contains($theme->canonical_key, '_expensive')
            || str_contains($theme->canonical_key, '_unreliable')) {
            return [SentimentClass::Negative, true];
        }

        return [$fallback ?? SentimentClass::Neutral, false];
    }

    /**
     * Whether a negation marker appears in the few words immediately before the match.
     */
    protected function isNegated(string $normalizedText, int $matchPosition): bool
    {
        $preceding = trim(substr($normalizedText, 0, $matchPosition));
        if ($preceding === '') {
            return false;
        }

        $words = array_slice(explode(' ', $preceding), -self::NEGATION_WINDOW);

        return count(array_intersect($words, self::NEGATION_MARKERS)) > 0;
    }

    protected function flip(SentimentClass $sentiment): SentimentClass
    {
        return match ($sentiment) {
            SentimentClass::Positive => SentimentClass::Negative,
            SentimentClass::Negative => SentimentClass::Positive,
            SentimentClass::Neutral => SentimentClass::Neutral,
        };
    }
}
