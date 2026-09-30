<?php

namespace App\Rules;

use App\Services\PhotoStorage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Refuses an image whose declared dimensions would exhaust memory when it is
 * decoded, whatever its size on disk. Reads only the header.
 */
class DecodablePhoto implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        $info = @getimagesize($value->getRealPath());

        // Not an image at all is the image rule's job, not this one's.
        if (! $info) {
            return;
        }

        if (! PhotoStorage::fitsInMemory((int) $info[0], (int) $info[1])) {
            $fail(__('common.photo_too_large', ['width' => $info[0], 'height' => $info[1]]));
        }
    }
}
