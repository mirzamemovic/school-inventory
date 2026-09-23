<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Support\AcademicYear;
use Illuminate\Contracts\View\View;

/**
 * The one exception to "there is no teacher login".
 *
 * Class teachers need to see who still owes books in June. Twenty accounts for
 * people who touch the system four times a year means twenty forgotten
 * passwords, so this is a signed, read-only, per-class link instead: sent over
 * WhatsApp, printable, expiring at the end of the academic year, and
 * regenerable if it leaks (spec section 3.1).
 */
class ClassDebtController extends Controller
{
    public function __invoke(string $class): View
    {
        $students = Person::query()
            ->students()
            ->active()
            ->inClass($class)
            ->with(['outstandingLoans.title'])
            ->orderBy('name')
            ->get()
            ->filter(fn (Person $person) => $person->outstandingLoans->isNotEmpty())
            ->values();

        return view('public.class-debts', [
            'class' => $class,
            'students' => $students,
            'academicYear' => AcademicYear::current(),
        ]);
    }
}
