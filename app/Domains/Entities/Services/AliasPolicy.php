<?php

namespace App\Domains\Entities\Services;

class AliasPolicy
{
    /**
     * Whether a normalized alias is allowed to identify an entity at all.
     */
    public static function isUsable(string $normalizedAlias): bool
    {
        return $normalizedAlias !== ''
            && ! in_array($normalizedAlias, (array) config('entity_matching.blocked_aliases', []), true);
    }

    /**
     * Whether an everyday-word brand alias ("jago") has a supporting context word in the text.
     */
    public static function hasRequiredContext(string $normalizedText, string $normalizedAlias): bool
    {
        $contextWords = (array) config('entity_matching.context_required_aliases.'.$normalizedAlias, []);

        if ($contextWords === []) {
            return true;
        }

        foreach ($contextWords as $word) {
            if (str_contains(" {$normalizedText} ", " {$word} ")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Short letter-only aliases are ambiguous in lowercase ("ga", "xl", "kai").
     */
    public static function requiresUppercase(string $normalizedAlias): bool
    {
        return preg_match('/^\p{L}+$/u', $normalizedAlias) === 1
            && mb_strlen($normalizedAlias, 'UTF-8') <= (int) config('entity_matching.uppercase_only_max_length', 3);
    }

    /**
     * Whether the alias appears as its own capitalised word in the original text.
     */
    public static function appearsUppercase(string $rawText, string $normalizedAlias): bool
    {
        $pattern = '/(?<![\p{L}\p{N}])'.preg_quote(mb_strtoupper($normalizedAlias, 'UTF-8'), '/').'(?![\p{L}\p{N}])/u';

        return preg_match($pattern, $rawText) === 1;
    }

    /**
     * Whether the alias carries a digit, i.e. names a model ("s24", "iphone 15") rather than a brand.
     */
    public static function isModelNumber(string $normalizedAlias): bool
    {
        return preg_match('/\d/', $normalizedAlias) === 1;
    }

    /**
     * Pattern for a model alias as its own phrase in normalized text, not followed by a variant
     * suffix token ("s24 fe"). Any one standalone occurrence is enough to match.
     */
    public static function standaloneModelPattern(string $normalizedAlias): string
    {
        $variants = implode('|', array_map(
            static fn (string $suffix): string => preg_quote($suffix, '/'),
            (array) config('entity_matching.model_variant_suffixes', [])
        ));
        $boundary = '(?![\p{L}\p{N}])';
        $notVariant = $variants === '' ? '' : "(?!\s+(?:{$variants}){$boundary})";

        return '/(?<![\p{L}\p{N}])'.preg_quote($normalizedAlias, '/').$boundary.$notVariant.'/u';
    }
}
