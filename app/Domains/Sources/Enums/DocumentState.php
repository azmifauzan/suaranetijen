<?php

namespace App\Domains\Sources\Enums;

enum DocumentState: string
{
    case Discovered = 'discovered';
    case Fetching = 'fetching';
    case Fetched = 'fetched';
    case Failed = 'failed';
    case Expired = 'expired';
}
