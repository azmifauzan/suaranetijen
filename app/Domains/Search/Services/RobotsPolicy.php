<?php

namespace App\Domains\Search\Services;

class RobotsPolicy
{
    protected static ?string $current = null;

    /**
     * Set the explicit robots policy string for the current request.
     */
    public static function set(string $policy): void
    {
        static::$current = $policy;
    }

    /**
     * Mark the current request as noindex (defaulting to noindex, follow).
     */
    public static function noindex(bool $follow = true): void
    {
        static::$current = $follow ? 'noindex, follow' : 'noindex, nofollow';
    }

    /**
     * Get the resolved robots policy string.
     */
    public static function get(): string
    {
        return static::$current ?? 'index, follow';
    }

    /**
     * Check if the current policy indicates noindex.
     */
    public static function isNoindex(): bool
    {
        return static::get() !== 'index, follow';
    }

    /**
     * Reset the policy state (useful between tests).
     */
    public static function reset(): void
    {
        static::$current = null;
    }
}
