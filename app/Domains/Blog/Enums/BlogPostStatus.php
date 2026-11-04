<?php

namespace App\Domains\Blog\Enums;

enum BlogPostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Terbit',
        };
    }
}
