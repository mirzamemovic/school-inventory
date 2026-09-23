<?php

namespace App\Enums;

enum LoanCondition: string
{
    case Good = 'good';
    case Damaged = 'damaged';
    case Lost = 'lost';

    public function label(): string
    {
        return __('books.condition.'.$this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Good => 'bg-status-ok-soft text-status-ok',
            self::Damaged => 'bg-status-attention-soft text-status-attention',
            self::Lost => 'bg-status-broken-soft text-status-broken',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
