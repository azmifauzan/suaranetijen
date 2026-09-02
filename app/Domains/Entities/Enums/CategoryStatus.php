<?php

namespace App\Domains\Entities\Enums;

enum CategoryStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';

    /**
     * Determine if the category status is active.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
