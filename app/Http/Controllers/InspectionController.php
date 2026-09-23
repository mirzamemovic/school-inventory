<?php

namespace App\Http\Controllers;

use App\Enums\ActorType;
use App\Enums\InspectionResult;
use App\Models\Asset;
use App\Models\BlockedDevice;
use App\Services\AuditLogger;
use App\Services\DeviceIdentity;
use App\Services\InspectionRecorder;
use App\Support\Actor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Inspection logging happens on the public scan page: whoever walks round with
 * the clipboard does not have an account and should not need one
 * (spec section 10, phase 4).
 */
class InspectionController extends Controller
{
    public function __construct(
        private readonly DeviceIdentity $device,
        private readonly InspectionRecorder $recorder,
        private readonly AuditLogger $log,
    ) {}

    public function create(Request $request, string $tag): View|Response|RedirectResponse
    {
        $asset = $this->findAsset($tag);

        if (! $asset instanceof Asset) {
            return $asset;
        }

        if (! $asset->needs_inspection) {
            return redirect()->route('scan', $tag);
        }

        return view('public.inspection', [
            'tag' => $asset->asset_tag,
            'name' => $asset->displayName(),
            'room' => $asset->room?->name,
            'knownName' => $this->device->name($request),
            'lastInspectedAt' => $asset->last_inspected_at,
        ]);
    }

    public function store(Request $request, string $tag): RedirectResponse|Response
    {
        $asset = $this->findAsset($tag);

        if (! $asset instanceof Asset) {
            return $asset;
        }

        $token = $this->device->token($request);

        if (BlockedDevice::blocks($token)) {
            return response()->view('public.too-many', status: 429);
        }

        $validated = $request->validate([
            'inspector_name' => ['required', 'string', 'min:2', 'max:60'],
            'result' => ['required', Rule::enum(InspectionResult::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = InspectionResult::from($validated['result']);

        $this->recorder->record(
            asset: $asset,
            result: $result,
            inspectorName: $validated['inspector_name'],
            notes: $validated['notes'] ?? null,
            actor: new Actor(ActorType::Public, null, $validated['inspector_name'], $token),
            deviceToken: $token,
        );

        return redirect()
            ->route('scan', $asset->asset_tag)
            ->with('status', $result === InspectionResult::Pass
                ? __('inspections.pass_thanks')
                : __('inspections.fail_thanks'));
    }

    private function findAsset(string $tag): Asset|Response
    {
        $asset = Asset::query()->with('room')->where('asset_tag', $tag)->first();

        return $asset ?? response()->view('public.not-found', ['tag' => $tag], 404);
    }
}
