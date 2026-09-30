<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Support\AcademicYear;
use App\Support\EventAction;
use Illuminate\Support\Facades\URL;

/**
 * The signed, read-only, per-class book-debt link (spec section 3.1).
 *
 * A signed URL alone cannot be withdrawn: the same class and the same expiry
 * date produce the same signature every time, so "regenerating" it hands back
 * the link that leaked. Each class therefore carries a version number inside
 * the signed part of the link. Making a new link moves the version on, and
 * every copy of the old one stops working at once — without touching APP_KEY,
 * which would sign everybody out.
 *
 * The version lives in the settings table under its own key, so this needs no
 * schema change.
 */
class ClassDebtLinks
{
    public function __construct(private readonly AuditLogger $log) {}

    public function urlFor(string $class): string
    {
        return URL::temporarySignedRoute('class.debts', AcademicYear::endsOn(), [
            'class' => $class,
            'v' => $this->version($class),
        ]);
    }

    public function isCurrent(string $class, mixed $version): bool
    {
        return (string) $version === (string) $this->version($class);
    }

    public function replace(string $class, User $by): void
    {
        $next = $this->version($class) + 1;

        Setting::put($this->key($class), (string) $next);

        $this->log->record($by, EventAction::CLASS_LINK_REPLACED, [
            'class' => $class,
            'version' => $next,
        ]);
    }

    private function version(string $class): int
    {
        return max(1, (int) Setting::get($this->key($class), 1));
    }

    private function key(string $class): string
    {
        return 'class_link_version:'.$class;
    }
}
