<?php

namespace App\Domains\Search\Enums;

enum SearchLandingPageStatus: string
{
    case Candidate = 'candidate';
    case Draft = 'draft';
    case Published = 'published';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Candidate => 'Kandidat',
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Rejected => 'Ditolak',
        };
    }
}
