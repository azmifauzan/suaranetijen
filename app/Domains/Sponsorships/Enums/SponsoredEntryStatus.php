<?php

namespace App\Domains\Sponsorships\Enums;

enum SponsoredEntryStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Paused = 'paused';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Pembayaran',
            self::Active => 'Aktif',
            self::Paused => 'Dijeda',
            self::Removed => 'Dihapus',
        };
    }
}
