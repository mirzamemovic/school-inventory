<?php

namespace App\Enums;

enum LicenseType: string
{
    case Software = 'software';
    case Domain = 'domain';
    case Hosting = 'hosting';
    case Antivirus = 'antivirus';
    case Other = 'other';

    public function label(): string
    {
        return __('licenses.type.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
