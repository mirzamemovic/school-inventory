<?php

namespace App\Http\Controllers;

use App\Enums\ActorType;
use App\Models\BlockedDevice;
use App\Models\Room;
use App\Models\Supply;
use App\Services\AuditLogger;
use App\Services\DeviceIdentity;
use App\Support\Actor;
use App\Support\EventAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * One QR on the cupboard door. No login, and nothing here is worth stealing:
 * the page says how much paper is left, which is not a shopping list.
 */
class PublicCupboardController extends Controller
{
    public function __construct(
        private readonly DeviceIdentity $device,
        private readonly AuditLogger $log,
    ) {}

    public function show(Room $room): View
    {
        return view('public.cupboard', [
            'room' => $room,
            'supplies' => Supply::query()->where('room_id', $room->id)->orderBy('name')->get(),
        ]);
    }

    /** "I took some" — one tap, no form. */
    public function take(Request $request, Room $room, Supply $supply): RedirectResponse|Response
    {
        $token = $this->device->token($request);

        if (BlockedDevice::blocks($token)) {
            return response()->view('public.too-many', status: 429);
        }

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $before = $supply->quantity;
        $supply->update(['quantity' => max(0, $before - (int) $validated['quantity'])]);

        $this->log->record($supply, EventAction::SUPPLY_TAKEN, [
            'quantity' => ['from' => $before, 'to' => $supply->quantity],
            'taken' => (int) $validated['quantity'],
        ], $this->publicActor($request, $token));

        return redirect()
            ->route('cupboard', $room)
            ->with('status', __('supplies.taken_thanks', ['name' => $supply->name]));
    }

    /**
     * Recount overwrites the number outright. Reconciling what is actually on
     * the shelf is more reliable than catching every withdrawal
     * (spec section 8).
     *
     * From this page a recount can only confirm the number or bring it down.
     * Raising it is the one thing an anonymous tap could use to silence a
     * warning — paper set to 9999 is never "running low" again — and stock
     * only goes up when a delivery arrives, which the office records.
     */
    public function recount(Request $request, Room $room, Supply $supply): RedirectResponse|Response
    {
        $token = $this->device->token($request);

        if (BlockedDevice::blocks($token)) {
            return response()->view('public.too-many', status: 429);
        }

        $validated = $request->validate(
            ['quantity' => ['required', 'integer', 'min:0', 'max:'.$supply->quantity]],
            ['quantity.max' => __('supplies.recount_too_high', ['count' => $supply->quantity])],
        );

        $before = $supply->quantity;

        $supply->update([
            'quantity' => (int) $validated['quantity'],
            'last_counted_at' => now(),
        ]);

        $this->log->record($supply, EventAction::SUPPLY_RECOUNTED, [
            'quantity' => ['from' => $before, 'to' => $supply->quantity],
        ], $this->publicActor($request, $token));

        return redirect()
            ->route('cupboard', $room)
            ->with('status', __('supplies.recounted_thanks', ['name' => $supply->name]));
    }

    private function publicActor(Request $request, string $token): Actor
    {
        $name = $this->device->name($request);

        return new Actor(
            $this->device->type($request)?->actorType() ?? ActorType::Public,
            null,
            $name ?: __('activity.someone'),
            $token,
        );
    }
}
