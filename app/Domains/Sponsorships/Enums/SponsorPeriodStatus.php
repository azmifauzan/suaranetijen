<?php

namespace App\Domains\Sponsorships\Enums;

enum SponsorPeriodStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Aktif',
            self::Closed => 'Selesai',
        };
    }
}
