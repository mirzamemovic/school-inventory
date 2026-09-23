<?php

namespace App\Enums;

/**
 * Who did the thing. Public reporting has no password, so anything that is not
 * `admin` or `system` is self-declared and must never be displayed as if it
 * were verified (spec section 5).
 */
enum ActorType: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Student = 'student';
    case Public = 'public';
    case System = 'system';

    public function isVerified(): bool
    {
        return $this === self::Admin || $this === self::System;
    }

    public function label(): string
    {
        return __('activity.actor.'.$this->value);
    }
}
