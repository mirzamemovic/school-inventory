<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AccountStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * An account that can sign in. Every account is an admin: there are no roles
 * and no limited accounts (spec §3, §19).
 *
 * The password is never mass-assignable. It is set in exactly two ways: by the
 * person themselves (the once-only link, "Change my password", or the
 * user:password command), or scrambled to something nobody knows when an
 * account is made or reset. Nothing can read it back.
 */
#[Fillable(['name', 'email'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * A school this size needs the director and a backup. The third place is
     * for a handover, when a new secretary starts before the old one leaves.
     */
    public const MAX_ACTIVE = 3;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'deactivated_at' => 'datetime',
            'password_set_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /** @param  Builder<User>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('deactivated_at');
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * Made, or reset, and the person has not yet chosen a password with their
     * link. The stored password is random and known to nobody.
     */
    public function isWaitingForPassword(): bool
    {
        return $this->password_set_at === null;
    }

    public function canSignIn(): bool
    {
        return ! $this->isDeactivated() && ! $this->isWaitingForPassword();
    }

    public function status(): AccountStatus
    {
        return match (true) {
            $this->isDeactivated() => AccountStatus::Deactivated,
            $this->isWaitingForPassword() => AccountStatus::Waiting,
            default => AccountStatus::Active,
        };
    }
}
