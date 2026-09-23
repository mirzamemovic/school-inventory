<?php

namespace App\Support;

use App\Enums\ActorType;
use App\Models\User;

/**
 * Who performed an action. Only admin and system actors are verified; every
 * other actor named themselves on a page with no password.
 */
final readonly class Actor
{
    public function __construct(
        public ActorType $type,
        public ?int $id = null,
        public ?string $name = null,
        public ?string $deviceToken = null,
    ) {}

    public static function admin(User $user): self
    {
        return new self(ActorType::Admin, $user->id, $user->name);
    }

    public static function system(): self
    {
        return new self(ActorType::System, null, __('activity.the_system'));
    }

    public static function current(): self
    {
        $user = auth()->user();

        return $user ? self::admin($user) : self::system();
    }
}
