<?php

namespace App\Enums;

enum InspectionResult: string
{
    case Pass = 'pass';
    case Fail = 'fail';

    public function label(): string
    {
        return __('inspections.result.'.$this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pass => 'bg-status-ok-soft text-status-ok',
            self::Fail => 'bg-status-broken-soft text-status-broken',
        };
    }
}
