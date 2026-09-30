<?php

namespace App\Domains\Search\Enums;

enum SearchLandingPageSource: string
{
    case SearchQuery = 'search_query';
    case CategoryTheme = 'category_theme';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::SearchQuery => 'Pencarian Pengguna',
            self::CategoryTheme => 'Kategori & Tema',
            self::Manual => 'Manual',
        };
    }
}
