<?php

namespace App\Domains\Entities\Services;

class TextNormalizer
{
    /**
     * Normalize text by lowercasing, removing punctuation, and collapsing whitespace.
     * Shared across search queries, entity aliases, and entity matching.
     */
    public static function normalize(string $text): string
    {
        // Lowercase UTF-8 string
        $text = mb_strtolower($text, 'UTF-8');

        // Replace punctuation, symbols, and non-alphanumeric characters with spaces
        // Keeps letters, numbers, and basic unicode word characters
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        // Collapse multiple whitespace characters into a single space
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }
}
