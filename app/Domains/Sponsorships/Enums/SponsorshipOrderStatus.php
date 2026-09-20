<?php

namespace App\Domains\Sponsorships\Enums;

enum SponsorshipOrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Pembayaran',
            self::Paid => 'Terkonfirmasi',
            self::Expired => 'Kadaluarsa',
            self::Failed => 'Gagal',
            self::Refunded => 'Dikembalikan',
        };
    }
}
