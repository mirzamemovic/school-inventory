<?php

namespace App\Models;

use App\Enums\SupplyUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'unit', 'quantity', 'minimum_level', 'room_id', 'storage_location', 'last_counted_at'])]
class Supply extends Model
{
    use HasFactory;

    protected $table = 'supplies';

    protected function casts(): array
    {
        return [
            'unit' => SupplyUnit::class,
            'quantity' => 'integer',
            'minimum_level' => 'integer',
            'last_counted_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function isLow(): bool
    {
        return $this->quantity <= $this->minimum_level;
    }

    public function scopeLow(Builder $query): Builder
    {
        return $query->whereColumn('quantity', '<=', 'minimum_level');
    }

    public function describeQuantity(): string
    {
        return $this->quantity.' '.$this->unit->label();
    }
}
