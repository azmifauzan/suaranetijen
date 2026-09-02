<?php

namespace App\Domains\Search\Services;

class TrigramSimilarity
{
    /**
     * Compute trigram similarity between two strings, matching PostgreSQL's pg_trgm similarity().
     */
    public static function similarity(?string $a, ?string $b): float
    {
        if ($a === null || $b === null || $a === '' || $b === '') {
            return 0.0;
        }

        $t1 = self::extractTrigrams($a);
        $t2 = self::extractTrigrams($b);

        $unionCount = count(array_unique(array_merge($t1, $t2)));

        if ($unionCount === 0) {
            return 0.0;
        }

        $intersectCount = count(array_intersect($t1, $t2));

        return (float) ($intersectCount / $unionCount);
    }

    /**
     * Extract unique trigrams from a string using word-based padding per pg_trgm specification.
     *
     * @return list<string>
     */
    public static function extractTrigrams(string $str): array
    {
        $words = preg_split('/\s+/u', trim(mb_strtolower($str, 'UTF-8')), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false || empty($words)) {
            return [];
        }

        $trigrams = [];
        foreach ($words as $w) {
            $padded = '  '.$w.' ';
            $len = mb_strlen($padded, 'UTF-8');
            for ($i = 0; $i < $len - 2; $i++) {
                $trigrams[] = mb_substr($padded, $i, 3, 'UTF-8');
            }
        }

        return array_values(array_unique($trigrams));
    }

    /**
     * Register similarity() and greatest() functions on a SQLite PDO instance.
     */
    public static function registerSqliteFunctions(\PDO $pdo): void
    {
        $register = function (string $name, callable $fn, int $numArgs = -1) use ($pdo): void {
            if (method_exists($pdo, 'createFunction')) {
                $pdo->createFunction($name, $fn, $numArgs);
            } elseif (method_exists($pdo, 'sqliteCreateFunction')) {
                $pdo->sqliteCreateFunction($name, $fn, $numArgs);
            }
        };

        $register('similarity', fn (?string $a, ?string $b): float => self::similarity($a, $b), 2);
        $register('greatest', fn (...$args): mixed => ! empty($args) ? max($args) : null, -1);
    }
}
