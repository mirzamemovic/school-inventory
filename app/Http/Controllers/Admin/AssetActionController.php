<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssetStatus;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Person;
use App\Models\Room;
use App\Services\AuditLogger;
use App\Support\EventAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The small, single-purpose actions from the asset detail screen. Each one is
 * one decision, so none of them opens a form with eight fields.
 */
class AssetActionController extends Controller
{
    public function __construct(private readonly AuditLogger $log) {}

    public function move(Request $request, Asset $asset): RedirectResponse
    {
        $validated = $request->validate([
            'room_id' => ['required', Rule::exists('rooms', 'id')],
        ]);

        $from = $asset->room?->name;
        $asset->update($validated);
        $to = Room::query()->find($validated['room_id'])?->name;

        $this->log->record($asset, EventAction::ASSET_MOVED, [
            'room' => ['from' => $from, 'to' => $to],
        ]);

        return back()->with('status', __('assets.moved', ['room' => $to]));
    }

    /**
     * Assigning a custodian does not move the item out of its room. If it did,
     * somebody doing the annual paper count would find a laptop the system
     * claims is not there (spec section 5).
     */
    public function assign(Request $request, Asset $asset): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_person_id' => ['nullable', Rule::exists('people', 'id')],
        ]);

        $from = $asset->assignedPerson?->name;
        $asset->update(['assigned_person_id' => $validated['assigned_person_id'] ?? null]);
        $to = Person::query()->find($validated['assigned_person_id'] ?? null)?->name;

        $this->log->record($asset, EventAction::ASSET_ASSIGNED, [
            'person' => ['from' => $from, 'to' => $to],
        ]);

        return back()->with('status', $to
            ? __('assets.assigned', ['name' => $to])
            : __('assets.unassigned_person'));
    }

    public function changeStatus(Request $request, Asset $asset): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(collect(AssetStatus::assignable())->map->value)],
        ]);

        $from = $asset->status;
        $asset->update($validated);

        $this->log->record($asset, EventAction::ASSET_STATUS_CHANGED, [
            'status' => ['from' => $from->value, 'to' => $asset->status->value],
        ]);

        return back()->with('status', __('assets.status_changed', ['status' => $asset->status->label()]));
    }

    public function retire(Request $request, Asset $asset): RedirectResponse
    {
        $validated = $request->validate([
            'disposal_method' => ['nullable', 'string', 'max:255'],
        ]);

        $asset->update([
            'status' => AssetStatus::Retired,
            'disposal_date' => now()->toDateString(),
            'disposal_method' => $validated['disposal_method'] ?? null,
        ]);

        $this->log->record($asset, EventAction::ASSET_RETIRED, [
            'disposal_method' => $validated['disposal_method'] ?? null,
        ]);

        return back()->with('status', __('assets.retired'));
    }
}
