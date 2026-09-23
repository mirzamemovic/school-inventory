<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LicenseRequest;
use App\Models\Asset;
use App\Models\License;
use App\Services\AuditLogger;
use App\Support\EventAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Licences sit under Manage, next to inspections, because both are "a dated
 * thing that goes overdue" (spec section 10). There is no sixth navigation
 * item and no cost reporting of any kind here.
 */
class LicenseController extends Controller
{
    private const EXPIRY_WINDOW_DAYS = 60;

    public function __construct(private readonly AuditLogger $log) {}

    public function index(Request $request): View
    {
        return view('admin.licenses.index', [
            'licenses' => License::query()
                ->search($request->string('q'))
                ->byExpiry()
                ->get(),
            'windowDays' => self::EXPIRY_WINDOW_DAYS,
        ]);
    }

    public function create(): View
    {
        return view('admin.licenses.create', $this->formData(new License));
    }

    public function store(LicenseRequest $request): RedirectResponse
    {
        $license = License::query()->create($request->validated());

        $this->log->record($license, EventAction::LICENCE_CREATED, [
            'name' => $license->name,
            'vendor' => $license->vendor,
            'expires_at' => $license->expires_at?->toDateString(),
        ]);

        return redirect()
            ->route('admin.licenses.show', $license)
            ->with('status', __('licenses.added'));
    }

    public function show(License $license): View
    {
        return view('admin.licenses.show', [
            'license' => $license->load(['asset', 'events']),
            'windowDays' => self::EXPIRY_WINDOW_DAYS,
        ]);
    }

    public function edit(License $license): View
    {
        return view('admin.licenses.edit', $this->formData($license));
    }

    public function update(LicenseRequest $request, License $license): RedirectResponse
    {
        $before = $this->snapshot($license);

        $license->update($request->validated());

        $changes = $this->log->changes($before, $this->snapshot($license->refresh()));

        if ($changes !== []) {
            $this->log->record($license, EventAction::LICENCE_EDITED, $changes);
        }

        return redirect()
            ->route('admin.licenses.show', $license)
            ->with('status', __('licenses.changes_saved'));
    }

    /**
     * Asks only for the new expiry date and, optionally, an updated cost. The
     * old and new dates go into the event meta rather than into a renewals
     * table (spec section 5).
     */
    public function renew(Request $request, License $license): RedirectResponse
    {
        $validated = $request->validate([
            'expires_at' => ['required', 'date'],
            'renewal_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $from = $license->expires_at?->toDateString();

        $license->update(array_filter([
            'expires_at' => $validated['expires_at'],
            'renewal_cost' => $validated['renewal_cost'] ?? null,
        ], fn ($value) => $value !== null));

        $this->log->record($license, EventAction::LICENCE_RENEWED, [
            'expires_at' => [
                'from' => $from,
                'to' => $license->expires_at->toDateString(),
            ],
            'renewal_cost' => $validated['renewal_cost'] ?? null,
        ]);

        return redirect()
            ->route('admin.licenses.show', $license)
            ->with('status', __('licenses.renewed', [
                'date' => $license->expires_at->format('j F Y'),
            ]));
    }

    /**
     * The licence row goes; the audit rows stay, because the log is append
     * only. The name is copied into meta so the deletion still reads as a
     * sentence once the subject is gone.
     */
    public function destroy(License $license): RedirectResponse
    {
        $name = $license->name;

        $this->log->record($license, EventAction::LICENCE_DELETED, [
            'name' => $name,
            'vendor' => $license->vendor,
        ]);

        $license->delete();

        return redirect()
            ->route('admin.licenses.index')
            ->with('status', __('licenses.deleted', ['name' => $name]));
    }

    /** @return array<string, mixed> */
    private function snapshot(License $license): array
    {
        return [
            'name' => $license->name,
            'vendor' => $license->vendor,
            'type' => $license->type?->value,
            'licence_model' => $license->licence_model?->value,
            'seats_total' => $license->seats_total,
            'seats_used' => $license->seats_used,
            'expires_at' => $license->expires_at?->toDateString(),
            'renewal_cost' => $license->renewal_cost,
            'auto_renew' => $license->auto_renew,
            'account_reference' => $license->account_reference,
            'asset' => $license->asset?->asset_tag,
            'notes' => $license->notes,
        ];
    }

    /** @return array<string, mixed> */
    private function formData(License $license): array
    {
        return [
            'license' => $license,
            // Only for tying an OEM licence to one machine.
            'assets' => Asset::query()
                ->whereNotNull('name')
                ->orderBy('name')
                ->get(),
        ];
    }
}
