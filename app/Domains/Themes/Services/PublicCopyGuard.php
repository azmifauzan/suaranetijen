<?php

namespace App\Domains\Themes\Services;

class PublicCopyGuard
{
    /**
     * docs/25 copy rules + SEO rules: no percentages, no superlatives, no handles/links.
     */
    public static function isAllowed(string $text, int $maxChars): bool
    {
        return $text !== ''
            && mb_strlen($text) <= $maxChars
            && ! str_contains($text, '%')
            && ! preg_match('/\b(terbaik|terburuk|persen|percent)\b|@\w|https?:\/\//iu', $text);
    }

    /**
     * Themes fit to quote in a snippet, FAQ or comparison. Superlative labels ("Samsung terbaik") are
     * skipped, not stripped, because stripping leaves a meaningless fragment. Generic labels that name no
     * aspect ("Rekomendasi beli bagus", "Kualitas mobil jelek") are skipped too: quoted alone they say
     * nothing, and a brand often has both "Kualitas mobil bagus" and "Kualitas mobil jelek".
     *
     * @template T of array{display_label: string}
     *
     * @param  array<int, T>  $themes
     * @return array<int, T>
     */
    public static function publishableThemes(array $themes): array
    {
        // ponytail: pattern list from the most widespread labels on staging (Nov 2026); a theme-level "generic" flag if it keeps growing.
        $generic = '/^(?:(?:kualitas(?: \w+)?|ponsel|mobil|motor|produk|rekomendasi beli) (?:bagus|baik|jelek|buruk|biasa saja)|penyebutan tanpa penilaian|perbandingan dengan kompetitor)$/iu';

        return array_values(array_filter($themes, function (array $theme) use ($generic): bool {
            $label = trim($theme['display_label']);

            return $label !== ''
                && ! preg_match('/\b(terbaik|terburuk)\b/iu', $label)
                && ! preg_match($generic, $label);
        }));
    }
}
