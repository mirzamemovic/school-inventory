<?php

namespace App\Enums;

/**
 * Backs the `licence_model` column. Independent of whether a licence has an
 * expiry date: what drives "never expires" on screen is a null `expires_at`,
 * not this field.
 */
enum LicenseModel: string
{
    case Subscription = 'subscription';
    case Perpetual = 'perpetual';
    case Volume = 'volume';

    public function label(): string
    {
        return __('licenses.model.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $m) => [$m->value => $m->label()])->all();
    }
}
