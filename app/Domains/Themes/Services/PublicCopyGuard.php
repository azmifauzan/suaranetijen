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
}
