<?php

namespace App\Models;

use App\Enums\LicenseModel;
use App\Enums\LicenseType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * A licence is not an asset: no room, no condition, no status, no QR code, and
 * it cannot be reported broken (spec section 5).
 *
 * The one rule that governs almost everything here is that `expires_at` is
 * nullable, because a perpetual licence never expires. Null means "never
 * expires" — it must never read as overdue.
 */
#[Fillable([
    'name', 'vendor', 'type', 'licence_model',
    'seats_total', 'seats_used',
    'purchase_date', 'expires_at', 'renewal_cost', 'auto_renew',
    'account_reference', 'asset_id', 'notes',
])]
class License extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => LicenseType::class,
            'licence_model' => LicenseModel::class,
            'purchase_date' => 'date',
            'expires_at' => 'date',
            'renewal_cost' => 'decimal:2',
            'auto_renew' => 'boolean',
            'seats_total' => 'integer',
            'seats_used' => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** The single audit log absorbs licences as-is; there is no second one. */
    public function events(): MorphMany
    {
        return $this->morphMany(AssetEvent::class, 'subject')->latest('created_at')->latest('id');
    }

    /** A perpetual licence never expires, so it is never expired. */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->endOfDay()->isPast();
    }

    public function neverExpires(): bool
    {
        return $this->expires_at === null;
    }

    /** Due within the window but not yet gone. Perpetual licences are never due. */
    public function isExpiringSoon(int $days = 60): bool
    {
        return ! $this->neverExpires()
            && ! $this->isExpired()
            && $this->expires_at->endOfDay()->lessThanOrEqualTo(now()->addDays($days));
    }

    public function daysUntilExpiry(): ?int
    {
        return $this->expires_at
            ? (int) now()->startOfDay()->diffInDays($this->expires_at->startOfDay(), false)
            : null;
    }

    /**
     * Everything with a real expiry date falling on or before the cutoff,
     * including anything already past it — an expired licence has not stopped
     * needing attention.
     *
     * Perpetual licences are excluded outright: a null expiry is not a very old
     * one. This is the most likely bug in the feature, so it is a scope rather
     * than a condition repeated at each call site.
     */
    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days)->toDateString());
    }

    /** Soonest first, with perpetual licences last (spec section 8). */
    public function scopeByExpiry(Builder $query): Builder
    {
        return $query
            ->orderByRaw('expires_at is null')
            ->orderBy('expires_at')
            ->orderBy('name');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('vendor', 'like', "%{$term}%")
                ->orWhere('account_reference', 'like', "%{$term}%");
        });
    }

    /**
     * Same countdown, different meaning. A manual renewal running out means the
     * service stops; an automatic one means money leaves the account
     * (spec section 5).
     */
    public function warningKey(): string
    {
        return $this->auto_renew ? 'licenses.warning.auto' : 'licenses.warning.manual';
    }

    public function expiresOn(): ?Carbon
    {
        return $this->expires_at;
    }
}
