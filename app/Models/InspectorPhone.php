<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone the director set up, in person, to record official safety checks.
 *
 * Like the reporter cookie it identifies a device, not a person, and it is not
 * a login: it unlocks exactly one thing — an inspection from this phone counts
 * towards the schedule. Only the label is fillable; every date and the token
 * are set by the invite and set-up steps themselves.
 */
#[Fillable(['label'])]
class InspectorPhone extends Model
{
    use HasFactory;

    public const INVITE_MINUTES = 30;

    protected function casts(): array
    {
        return [
            'invite_expires_at' => 'datetime',
            'claimed_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** @param  Builder<InspectorPhone>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotNull('claimed_at')->whereNull('withdrawn_at');
    }

    /** Is this device one of the phones whose checks count? */
    public static function trusted(?string $deviceToken): ?self
    {
        if ($deviceToken === null || $deviceToken === '') {
            return null;
        }

        return static::query()->active()->where('device_token', $deviceToken)->first();
    }

    public function isActive(): bool
    {
        return $this->claimed_at !== null && $this->withdrawn_at === null;
    }

    /** Can the set-up link still be used? Once only, and not for long. */
    public function isClaimable(): bool
    {
        return $this->claimed_at === null
            && $this->withdrawn_at === null
            && $this->invite_expires_at?->isFuture() === true;
    }
}
