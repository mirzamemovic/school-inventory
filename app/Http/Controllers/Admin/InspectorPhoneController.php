<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InspectorPhone;
use App\Services\AuditLogger;
use App\Services\QrImage;
use App\Support\EventAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * The phones whose safety checks count.
 *
 * Setting one up happens in person: the director makes a code here and the
 * inspector scans it with their own phone there and then. The code works once
 * and runs out after half an hour, so a photo of the screen is worth nothing
 * by the afternoon.
 */
class InspectorPhoneController extends Controller
{
    public function __construct(private readonly AuditLogger $log) {}

    public function index(): View
    {
        return view('admin.inspectors.index', [
            'phones' => InspectorPhone::query()->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'min:2', 'max:80'],
        ]);

        $phone = new InspectorPhone(['label' => $validated['label']]);
        $phone->invite_expires_at = now()->addMinutes(InspectorPhone::INVITE_MINUTES);
        $phone->invited_by = $request->user()->id;
        $phone->save();

        $this->log->record($phone, EventAction::INSPECTOR_PHONE_INVITED, ['label' => $phone->label]);

        return redirect()
            ->route('admin.inspectors.show', $phone)
            ->with('status', __('inspections.phones.invited'));
    }

    public function show(InspectorPhone $phone, QrImage $qr): View
    {
        $setupUrl = $phone->isClaimable()
            ? URL::temporarySignedRoute('inspector-phone.setup', $phone->invite_expires_at, ['phone' => $phone])
            : null;

        return view('admin.inspectors.show', [
            'phone' => $phone,
            'setupUrl' => $setupUrl,
            'qrSvg' => $setupUrl ? $qr->svg($setupUrl) : null,
        ]);
    }

    public function withdraw(InspectorPhone $phone): RedirectResponse
    {
        if ($phone->withdrawn_at === null) {
            $phone->forceFill(['withdrawn_at' => now()])->save();

            $this->log->record($phone, EventAction::INSPECTOR_PHONE_WITHDRAWN, ['label' => $phone->label]);
        }

        return redirect()
            ->route('admin.inspectors.index')
            ->with('status', __('inspections.phones.withdrawn'));
    }
}
