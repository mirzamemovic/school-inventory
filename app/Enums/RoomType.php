<?php

namespace App\Enums;

enum RoomType: string
{
    case Classroom = 'classroom';
    case Lab = 'lab';
    case Office = 'office';
    case Storage = 'storage';
    case Corridor = 'corridor';
    case Offsite = 'offsite';

    public function label(): string
    {
        return __('rooms.type.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
