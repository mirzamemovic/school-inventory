<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\FundingSource;
use App\Exceptions\TagIsWriteOnce;
use App\Support\StickerUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * asset_tag and tag_sequence are deliberately absent from the fillable list.
 * They are set once by the tag generator and never mass-assigned.
 */
#[Fillable([
    'official_number', 'category_id', 'name', 'make', 'model', 'serial_number', 'photo_path',
    'purchase_date', 'purchase_price', 'supplier', 'invoice_ref', 'funding_source', 'warranty_end',
    'room_id', 'assigned_person_id', 'status', 'condition',
    'expected_life_years', 'disposal_date', 'disposal_method',
    'needs_inspection', 'inspection_interval_months', 'notes',
])]
class Asset extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'condition' => AssetCondition::class,
            'funding_source' => FundingSource::class,
            'purchase_date' => 'date',
            'warranty_end' => 'date',
            'disposal_date' => 'date',
            'purchase_price' => 'decimal:2',
            'needs_inspection' => 'boolean',
            'last_inspected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Asset $asset) {
            if ($asset->isDirty('asset_tag')) {
                throw TagIsWriteOnce::for($asset->getOriginal('asset_tag'), $asset->asset_tag);
            }

            if ($asset->isDirty('tag_sequence')) {
                throw TagIsWriteOnce::for(
                    (string) $asset->getOriginal('tag_sequence'),
                    (string) $asset->tag_sequence,
                );
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'asset_tag';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function events(): MorphMany
    {
        return $this->morphMany(AssetEvent::class, 'subject')->latest('created_at')->latest('id');
    }

    /**
     * Who is answerable for the item. Independent of room: a laptop has a home
     * base and a custodian, and both facts are true at once (spec section 5).
     */
    public function assignedPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'assigned_person_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class)->latest('inspected_at');
    }

    /** The newest check of any kind, counted or not. */
    public function latestInspection(): HasOne
    {
        return $this->hasOne(Inspection::class)->latestOfMany('inspected_at');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class)->latest('created_at');
    }

    /**
     * The one report that stops a second person filing a duplicate. There is
     * never more than one open at a time: additional information goes onto the
     * report that is already open.
     */
    public function openReport(): ?Report
    {
        return $this->reports()->stillOpen()->first();
    }

    /** A pre-printed label that has not been stuck to anything yet. */
    public function isBlank(): bool
    {
        return $this->status === AssetStatus::Unassigned;
    }

    public function displayName(): string
    {
        return $this->name ?: __('assets.blank_label');
    }

    public function scanUrl(): string
    {
        return StickerUrl::to('/a/'.$this->asset_tag);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('asset_tag', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('make', 'like', "%{$term}%")
                ->orWhere('model', 'like', "%{$term}%")
                ->orWhere('serial_number', 'like', "%{$term}%")
                ->orWhere('official_number', 'like', "%{$term}%");
        });
    }
}
