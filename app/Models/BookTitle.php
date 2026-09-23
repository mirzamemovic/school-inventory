<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'subject', 'grade', 'isbn', 'total_copies'])]
class BookTitle extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'grade' => 'integer',
            'total_copies' => 'integer',
        ];
    }

    public function loans(): HasMany
    {
        return $this->hasMany(BookLoan::class);
    }

    public function copiesOut(): int
    {
        return $this->loans()->whereNull('returned_at')->count();
    }

    public function copiesAvailable(): int
    {
        return max(0, $this->total_copies - $this->copiesOut());
    }
}
