<?php

namespace App\Domains\Entities\Enums;

enum EntityType: string
{
    case Brand = 'brand';
    case Product = 'product';
    case Service = 'service';

    /**
     * Get a human-readable label for the entity type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Brand => 'Brand',
            self::Product => 'Product',
            self::Service => 'Service',
        };
    }
}
