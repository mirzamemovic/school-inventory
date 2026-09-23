<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\RoomType;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Room;
use App\Services\AuditLogger;
use App\Support\EventAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Scan a blank label, fill in four fields, save and scan the next one.
 *
 * This loop must never dump the user back to a list: breaking it turns a
 * four-hour job into a two-day job (spec section 8).
 */
class BulkIntakeController extends Controller
{
    public function __construct(private readonly AuditLogger $log) {}

    /** Where every save returns to, with the tag box waiting and focused. */
    public function start(): View
    {
        return view('admin.intake.start', [
            'remaining' => Asset::query()->where('status', AssetStatus::Unassigned->value)->count(),
        ]);
    }

    /** Typing the tag by hand, for when the QR is scratched or the camera will not focus. */
    public function find(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'asset_tag' => ['required', 'string', 'max:32'],
        ]);

        $asset = Asset::query()
            ->where('asset_tag', strtoupper(trim($validated['asset_tag'])))
            ->first();

        if (! $asset) {
            return back()->withInput()->withErrors([
                'asset_tag' => __('intake.not_found', ['tag' => $validated['asset_tag']]),
            ]);
        }

        return redirect()->route('admin.intake.edit', $asset);
    }

    public function edit(Asset $asset): View|RedirectResponse
    {
        // Already filled in: send the admin to the full record rather than
        // silently overwriting somebody else's work.
        if (! $asset->isBlank()) {
            return redirect()
                ->route('admin.assets.show', $asset)
                ->with('status', __('intake.already_filled_in'));
        }

        return view('admin.intake.edit', [
            'asset' => $asset,
            'rooms' => Room::query()->orderBy('name')->get(),
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'room_id' => ['required', Rule::exists('rooms', 'id')],
            'condition' => ['required', Rule::enum(AssetCondition::class)],
        ]);

        $room = Room::query()->findOrFail($validated['room_id']);
        $category = Category::query()->findOrFail($validated['category_id']);

        $asset->fill($validated);

        // A fifth question nobody needs to be asked: something being labelled in
        // a storage room is in storage, and everything else is in use.
        $asset->status = $room->type === RoomType::Storage
            ? AssetStatus::InStorage
            : AssetStatus::InUse;

        // Compliance items are an inspection schedule, not inventory. The
        // category already knows which ones those are.
        if ($category->needs_inspection_default) {
            $asset->needs_inspection = true;
            $asset->inspection_interval_months = $category->default_inspection_interval_months;
        }

        $asset->save();

        $this->log->record($asset, EventAction::ASSET_CREATED, [
            'name' => $asset->name,
            'room' => $room->name,
            'via' => 'bulk intake',
        ]);

        // Straight back to the scan box, never to a list.
        return redirect()
            ->route('admin.intake.start')
            ->with('status', __('intake.saved', ['name' => $asset->name, 'tag' => $asset->asset_tag]));
    }
}
