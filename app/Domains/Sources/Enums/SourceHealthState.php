<?php

namespace App\Domains\Sources\Enums;

enum SourceHealthState: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Blocked = 'blocked';
    case PolicyDisabled = 'policy_disabled';
    case ParserBroken = 'parser_broken';
    case QuotaLimited = 'quota_limited';

    /**
     * Determine if the source is considered operational for crawl jobs.
     */
    public function isOperational(): bool
    {
        return match ($this) {
            self::Healthy, self::Degraded => true,
            self::Blocked, self::PolicyDisabled, self::ParserBroken, self::QuotaLimited => false,
        };
    }
}
