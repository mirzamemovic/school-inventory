<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'prefix', 'needs_inspection_default', 'default_inspection_interval_months'])]
class Category extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'needs_inspection_default' => 'boolean',
            'default_inspection_interval_months' => 'integer',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
