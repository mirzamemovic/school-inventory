<?php

namespace App\Enums;

enum SupplyUnit: string
{
    case Packs = 'packs';
    case Boxes = 'boxes';
    case Pieces = 'pieces';
    case Reams = 'reams';

    public function label(): string
    {
        return __('supplies.unit.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $u) => [$u->value => $u->label()])->all();
    }
}
