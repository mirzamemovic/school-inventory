<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The audit log is append only (spec section 5). If something was logged wrong,
 * log a correction on top of it.
 */
class AuditLogIsAppendOnly extends RuntimeException
{
    public static function onUpdate(): self
    {
        return new self('Audit log rows cannot be updated. Log a correction instead.');
    }

    public static function onDelete(): self
    {
        return new self('Audit log rows cannot be deleted. Log a correction instead.');
    }
}
