<?php

namespace App\Domains\Sources\Enums;

enum ProcessingState: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
