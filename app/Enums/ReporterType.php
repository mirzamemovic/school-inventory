<?php

namespace App\Enums;

enum ReporterType: string
{
    case Staff = 'staff';
    case Student = 'student';
    case Other = 'other';

    public function label(): string
    {
        return __('reports.reporter.'.$this->value);
    }

    public function actorType(): ActorType
    {
        return match ($this) {
            self::Staff => ActorType::Staff,
            self::Student => ActorType::Student,
            self::Other => ActorType::Public,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
