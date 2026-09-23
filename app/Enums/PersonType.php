<?php

namespace App\Enums;

enum PersonType: string
{
    case Staff = 'staff';
    case Student = 'student';

    public function label(): string
    {
        return __('people.type.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
