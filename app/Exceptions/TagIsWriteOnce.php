<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A tag is assigned once and never changes (spec section 6.2). The sticker is
 * physical and cannot be updated, so a tag that changes is a tag that lies
 * about the object it is stuck to.
 */
class TagIsWriteOnce extends RuntimeException
{
    public static function for(string $from, string $to): self
    {
        return new self("An asset tag cannot be changed once assigned (tried to change {$from} to {$to}).");
    }
}
