<?php

namespace App\Models;

use App\Enums\LoanCondition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['person_id', 'book_title_id', 'issued_at', 'due_at', 'returned_at', 'condition_on_return', 'notes', 'academic_year'])]
class BookLoan extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'due_at' => 'date',
            'returned_at' => 'date',
            'condition_on_return' => LoanCondition::class,
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function title(): BelongsTo
    {
        return $this->belongsTo(BookTitle::class, 'book_title_id');
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereNull('returned_at');
    }

    public function scopeForYear(Builder $query, string $academicYear): Builder
    {
        return $query->where('academic_year', $academicYear);
    }

    public function isOverdue(): bool
    {
        return $this->returned_at === null
            && $this->due_at !== null
            && $this->due_at->isPast();
    }
}
