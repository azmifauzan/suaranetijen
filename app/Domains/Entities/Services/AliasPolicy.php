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
}
