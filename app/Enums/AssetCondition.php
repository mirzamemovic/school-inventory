<?php

namespace App\Enums;

enum AssetCondition: string
{
    case Good = 'good';
    case NeedsAttention = 'needs_attention';
    case Broken = 'broken';

    public function label(): string
    {
        return __('assets.condition.'.$this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Good => 'bg-status-ok-soft text-status-ok',
            self::NeedsAttention => 'bg-status-attention-soft text-status-attention',
            self::Broken => 'bg-status-broken-soft text-status-broken',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
