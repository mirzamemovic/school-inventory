<?php

namespace App\Enums;

enum AssetStatus: string
{
    case InUse = 'in_use';
    case InStorage = 'in_storage';
    case UnderRepair = 'under_repair';
    case Retired = 'retired';
    case Lost = 'lost';
    case Unassigned = 'unassigned';

    public function label(): string
    {
        return __('assets.status.'.$this->value);
    }

    /**
     * Status colour only (spec section 9). The badge always carries the word as
     * well as the colour — some users are colourblind and all of them are
     * looking at a phone in a sunlit corridor.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::InUse => 'bg-status-ok-soft text-status-ok',
            self::InStorage => 'bg-grey-100 text-grey-700',
            self::UnderRepair => 'bg-status-broken-soft text-status-broken',
            self::Lost => 'bg-status-broken-soft text-status-broken',
            self::Retired => 'bg-status-inactive-soft text-status-inactive',
            self::Unassigned => 'bg-status-attention-soft text-status-attention',
        };
    }

    /** Statuses an admin may choose by hand. `unassigned` is set by the system only. */
    public static function assignable(): array
    {
        return array_filter(self::cases(), fn (self $s) => $s !== self::Unassigned);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::assignable())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
