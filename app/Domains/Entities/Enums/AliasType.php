<?php

namespace App\Domains\Entities\Enums;

enum AliasType: string
{
    case Primary = 'primary';
    case CommonVariant = 'common_variant';
    case Abbreviation = 'abbreviation';
    case Misspelling = 'misspelling';
}
