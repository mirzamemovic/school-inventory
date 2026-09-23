<?php

namespace App\Models;

use App\Enums\AssetCondition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['room_id', 'item_name', 'quantity', 'condition'])]
class RoomContent extends Model
{
    use HasFactory;

    protected $table = 'room_contents';

    protected function casts(): array
    {
        return [
            'condition' => AssetCondition::class,
            'quantity' => 'integer',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
