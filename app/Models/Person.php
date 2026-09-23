<?php

namespace App\Models;

use App\Enums\PersonType;
use App\Observers\PersonObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(PersonObserver::class)]
#[Fillable(['name', 'type', 'class', 'email', 'active'])]
class Person extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => PersonType::class,
            'active' => 'boolean',
            'left_at' => 'date',
        ];
    }

    public function loans(): HasMany
    {
        return $this->hasMany(BookLoan::class);
    }

    public function outstandingLoans(): HasMany
    {
        return $this->loans()->whereNull('returned_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeStudents(Builder $query): Builder
    {
        return $query->where('type', PersonType::Student->value);
    }

    public function scopeInClass(Builder $query, string $class): Builder
    {
        return $query->where('class', $class);
    }

    /** The grade is the leading number of the class name: "10a" is grade 10. */
    public function grade(): ?int
    {
        return preg_match('#^(\d+)#', (string) $this->class, $matches)
            ? (int) $matches[1]
            : null;
    }
}
