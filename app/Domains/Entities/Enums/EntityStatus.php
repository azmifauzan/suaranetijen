<?php

namespace App\Domains\Entities\Enums;

enum EntityStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';

    /**
     * Determine if the entity status is active.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
