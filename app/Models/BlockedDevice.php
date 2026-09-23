<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['device_token', 'note'])]
class BlockedDevice extends Model
{
    public static function blocks(?string $deviceToken): bool
    {
        return $deviceToken !== null
            && static::query()->where('device_token', $deviceToken)->exists();
    }
}
