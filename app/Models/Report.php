<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\ReporterType;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * Mass assignment here is the single most likely real vulnerability in this
 * codebase: the create path is fed by an anonymous stranger on a page with no
 * password. Only the four fields a reporter is allowed to set are fillable —
 * status, the resolution fields and every timestamp are absent on purpose, and
 * the create path builds the model field by field anyway (spec section 13).
 */
#[Fillable(['reporter_name', 'reporter_type', 'description', 'photo_path'])]
class Report extends Model
{
    use HasFactory;

    /**
     * The description is a TEXT column: 65,535 bytes on MySQL, which runs in
     * strict mode and refuses anything longer. Additions stop comfortably
     * short of that.
     */
    public const MAX_DESCRIPTION_BYTES = 60_000;

    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'reporter_type' => ReporterType::class,
            'asset_status_before' => AssetStatus::class,
            'asset_condition_before' => AssetCondition::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'first_viewed_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function events(): MorphMany
    {
        return $this->morphMany(AssetEvent::class, 'subject')->latest('created_at')->latest('id');
    }

    /**
     * Short, readable over the phone, and without the characters people misread
     * when copying a code off a screen.
     */
    public static function newReference(): string
    {
        do {
            $reference = Str::upper(Str::password(6, symbols: false, numbers: true, letters: true));
            $reference = str_replace(['0', 'O', '1', 'I', 'L'], ['2', '3', '4', '5', '6'], $reference);
        } while (static::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public function isOpen(): bool
    {
        return ! $this->status->isClosed();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::Open->value);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::InProgress->value);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereIn('status', [ReportStatus::Resolved->value, ReportStatus::Dismissed->value]);
    }

    public function scopeStillOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [ReportStatus::Open->value, ReportStatus::InProgress->value]);
    }

    /** Rows open more than seven days turn red (spec section 7B). */
    public function isStale(): bool
    {
        return $this->isOpen() && $this->created_at->diffInDays(now()) >= 7;
    }
}
