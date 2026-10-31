<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

/**
 * The address that goes on a sticker. A sticker cannot be updated, so this comes
 * from APP_URL and never from the request: printing once from a temporary
 * address must not put that address on every label in the building.
 *
 * Only stickers are pinned. Navigation, redirects and signed links keep
 * following the request, because a signed link is checked against the host it
 * arrives on, and because the site has to stay usable from wherever it is
 * opened. A signed link can simply be made again; a sticker cannot.
 */
final class StickerUrl
{
    public static function to(string $path): string
    {
        $root = rtrim((string) config('app.url'), '/');

        // Production is HTTPS only, and a plain-HTTP address on a sticker would
        // lean on that redirect for as long as the sticker lasts.
        if (app()->environment('production')) {
            $root = URL::formatRoot('https://', $root);
        }

        return $root.'/'.ltrim($path, '/');
    }
}
