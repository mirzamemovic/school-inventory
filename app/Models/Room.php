<?php

namespace App\Models;

use App\Enums\RoomType;
use App\Support\StickerUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'floor', 'type', 'last_counted_at'])]
class Room extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => RoomType::class,
            'last_counted_at' => 'datetime',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /** Unlabelled furniture, counted rather than tracked. */
    public function contents(): HasMany
    {
        return $this->hasMany(RoomContent::class);
    }

    public function supplies(): HasMany
    {
        return $this->hasMany(Supply::class);
    }

    public function label(): string
    {
        return $this->floor ? $this->name.' ('.$this->floor.')' : $this->name;
    }

    /** The address for the QR on this room's cupboard door. */
    public function cupboardUrl(): string
    {
        return StickerUrl::to('/c/'.$this->id);
    }
}
