<?php

namespace App\Domains\Sentiment\Enums;

enum Period: string
{
    case ThirtyDays = '30d';
    case NinetyDays = '90d';
    case OneYear = '365d';
    case All = 'all';

    /**
     * Return the lookback window in days (null for all-time).
     */
    public function days(): ?int
    {
        return match ($this) {
            self::ThirtyDays => 30,
            self::NinetyDays => 90,
            self::OneYear => 365,
            self::All => null,
        };
    }
}
