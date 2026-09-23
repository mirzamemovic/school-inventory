<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Support\PublicAssetView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * The page a phone camera lands on. No login, no redirect, no interstitial: if
 * a teacher scans a sticker and gets a login screen, the system has failed at
 * its main job (spec section 3.1).
 */
class PublicAssetController extends Controller
{
    public function show(string $tag): View|RedirectResponse|Response
    {
        $asset = Asset::query()->with('room')->where('asset_tag', $tag)->first();

        // A plain, readable page rather than a framework 404: whoever is
        // holding the phone has just read a damaged sticker, and telling them
        // to check the code is more use than an error screen.
        if (! $asset) {
            return response()->view('public.not-found', ['tag' => $tag], 404);
        }

        // Same sticker, same URL, more depth by session: the director scanning
        // in a corridor gets everything, an anonymous scan gets almost nothing.
        if (auth()->check()) {
            // Scanning a blank label during the labelling day is the start of
            // bulk intake, not a visit to an empty record (spec section 6.3).
            return $asset->isBlank()
                ? redirect()->route('admin.intake.edit', $asset)
                : redirect()->route('admin.assets.show', $asset);
        }

        return view('public.asset', [
            'asset' => PublicAssetView::from($asset, $asset->openReport()),
            'tag' => $asset->asset_tag,
        ]);
    }
}
