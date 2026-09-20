<?php

namespace App\Domains\Sponsorships\Enums;

enum SponsorshipRelayEventStatus: string
{
    case Received = 'received';
    case Delivered = 'delivered';
    case Ignored = 'ignored';
    case Failed = 'failed';
}
