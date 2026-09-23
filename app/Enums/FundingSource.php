<?php

namespace App\Enums;

/**
 * Donated and EU-project equipment carries different reporting and disposal
 * obligations in Kosovo than budget purchases (spec section 5).
 */
enum FundingSource: string
{
    case Budget = 'budget';
    case Donation = 'donation';
    case Project = 'project';
    case Unknown = 'unknown';

    public function label(): string
    {
        return __('assets.funding.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $f) => [$f->value => $f->label()])->all();
    }
}
