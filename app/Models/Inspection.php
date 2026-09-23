<?php

namespace App\Models;

use App\Enums\InspectionResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['asset_id', 'inspected_at', 'inspector_name', 'result', 'notes', 'next_due_at', 'device_token'])]
class Inspection extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'result' => InspectionResult::class,
            'inspected_at' => 'datetime',
            'next_due_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
