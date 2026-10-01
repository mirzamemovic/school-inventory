<?php

namespace App\Enums;

/**
 * Where an account stands, worked out from User rather than stored, so it can
 * never disagree with the columns it comes from.
 */
enum AccountStatus: string
{
    case Active = 'active';
    case Waiting = 'waiting';
    case Deactivated = 'deactivated';

    public function label(): string
    {
        return __('users.status.'.$this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Active => 'bg-status-ok-soft text-status-ok',
            self::Waiting => 'bg-status-attention-soft text-status-attention',
            self::Deactivated => 'bg-status-inactive-soft text-status-inactive',
        };
    }
}
