<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\CarbonImmutable;

/**
 * A Kosovo gymnasium year runs September to June, so it spans two calendar
 * years and is written "2026/2027". Book loans are due on the last day of it,
 * and a class debt link stops working then too.
 *
 * The year rolls over on the day it ends, not on the day the next one starts.
 * Over the summer — after books are due back but before term begins — anything
 * issued belongs to the year about to start, otherwise a book handed out in
 * August would arrive already overdue.
 */
final class AcademicYear
{
    /**
     * The director can pin the year in Settings, and that wins — but only while
     * it still means something. A pinned year that has already ended is
     * ignored, because otherwise the September nobody remembers to change it
     * every book gets a due date in the past and every class link is born
     * expired.
     */
    public static function current(): string
    {
        $stored = Setting::get('academic_year');

        $pinned = is_string($stored)
            && preg_match('#^\d{4}/\d{4}$#', $stored)
            && ! self::endsOn($stored)->isPast();

        return $pinned ? $stored : self::forDate(CarbonImmutable::now());
    }

    public static function forDate(CarbonImmutable $date): string
    {
        [$endMonth, $endDay] = self::endMonthAndDay();

        $pastThisYearsEnd = $date->month > $endMonth
            || ($date->month === $endMonth && $date->day > $endDay);

        $startYear = $pastThisYearsEnd ? $date->year : $date->year - 1;

        return $startYear.'/'.($startYear + 1);
    }

    /** The day everything borrowed this year has to be back. */
    public static function endsOn(?string $academicYear = null): CarbonImmutable
    {
        $academicYear ??= self::current();
        $endYear = (int) explode('/', $academicYear)[1];
        [$month, $day] = self::endMonthAndDay();

        return CarbonImmutable::create($endYear, $month, $day)->endOfDay();
    }

    /**
     * Book debts only matter between May and September: chasing them in
     * November is noise (spec section 8).
     */
    public static function isChasingSeason(): bool
    {
        return in_array(CarbonImmutable::now()->month, [5, 6, 7, 8, 9], true);
    }

    /** @return array{int, int} */
    private static function endMonthAndDay(): array
    {
        [$month, $day] = explode('-', (string) config('school.academic_year.ends'));

        return [(int) $month, (int) $day];
    }
}
