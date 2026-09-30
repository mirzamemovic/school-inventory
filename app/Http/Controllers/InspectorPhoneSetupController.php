<?php

namespace App\Http\Controllers;

use App\Enums\ActorType;
use App\Models\BlockedDevice;
use App\Models\InspectorPhone;
use App\Services\AuditLogger;
use App\Services\DeviceIdentity;
use App\Support\Actor;
use App\Support\EventAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The phone's side of being set up for safety checks, reached by scanning the
 * code on the director's screen. No login: the signed, once-only, half-hour
 * link is the whole permission.
 *
 * Opening the link changes nothing. Messaging apps fetch links to draw a
 * preview, and that fetch must not use up the code; only the button does.
 */
class InspectorPhoneSetupController extends Controller
{
    public function __construct(
        private readonly DeviceIdentity $device,
        private readonly AuditLogger $log,
    ) {}

    public function show(Request $request, InspectorPhone $phone): View|Response
    {
        if (! $phone->isClaimable()) {
            return response()->view('public.inspector-setup-unavailable', status: 410);
        }

        return view('public.inspector-setup', [
            // The same signed address, posted to, is what claims it.
            'action' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, InspectorPhone $phone): RedirectResponse|Response
    {
        $token = $this->device->token($request);

        if (BlockedDevice::blocks($token)) {
            return response()->view('public.too-many', status: 429);
        }

        if (! $phone->isClaimable()) {
            return response()->view('public.inspector-setup-unavailable', status: 410);
        }

        // A phone seen for the first time has no cookie yet; the token has to
        // outlive this request for the phone to be recognised tomorrow.
        $this->device->keep($request, $token);

        $phone->forceFill([
            'device_token' => $token,
            'claimed_at' => now(),
        ])->save();

        $this->log->record($phone, EventAction::INSPECTOR_PHONE_CLAIMED, ['label' => $phone->label], new Actor(
            ActorType::Public,
            null,
            $this->device->name($request) ?: __('activity.someone'),
            $token,
        ));

        return redirect()->route('inspector-phone.done')->with('phone_set_up', true);
    }

    public function done(Request $request): View
    {
        // Only straight after setting a phone up; otherwise there is nothing to say.
        abort_unless($request->session()->get('phone_set_up'), 404);

        return view('public.inspector-setup-done');
    }
}
