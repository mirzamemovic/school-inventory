<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupplyUnit;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Supply;
use App\Services\AuditLogger;
use App\Support\EventAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupplyController extends Controller
{
    public function __construct(private readonly AuditLogger $log) {}

    public function index(Request $request): View
    {
        return view('admin.supplies.index', [
            'supplies' => Supply::query()
                ->with('room')
                ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
                ->orderBy('name')
                ->get(),
            'rooms' => Room::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated) {
            $supply = Supply::query()->create($validated);

            $this->log->record($supply, EventAction::SUPPLY_ADDED, [
                'name' => $supply->name,
                'quantity' => $supply->quantity,
            ]);
        });

        return redirect()->route('admin.supplies.index')->with('status', __('supplies.added'));
    }

    public function edit(Supply $supply): View
    {
        return view('admin.supplies.edit', [
            'supply' => $supply,
            'rooms' => Room::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * The edit form can set the stock directly, which the +/− buttons and the
     * cupboard page both log; this has to as well, or stock could change with
     * no trace at all.
     */
    public function update(Request $request, Supply $supply): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($supply, $validated) {
            $before = $this->snapshot($supply);
            $supply->update($validated);

            $this->log->recordChanges($supply, EventAction::SUPPLY_EDITED, $before, $this->snapshot($supply->load('room')), $supply->name);
        });

        return redirect()->route('admin.supplies.index')->with('status', __('supplies.changes_saved'));
    }

    /**
     * The inline plus and minus on the list. One tap, one number, no form.
     * Answered over HTMX so the row updates without a page reload.
     */
    public function adjust(Request $request, Supply $supply): View|RedirectResponse
    {
        $validated = $request->validate([
            'by' => ['required', 'integer', 'min:-100', 'max:100'],
        ]);

        $before = $supply->quantity;
        $supply->update(['quantity' => max(0, $before + (int) $validated['by'])]);

        $this->log->record($supply, EventAction::SUPPLY_RECOUNTED, [
            'quantity' => ['from' => $before, 'to' => $supply->quantity],
        ]);

        if ($request->header('HX-Request')) {
            return view('admin.supplies._row', ['supply' => $supply->fresh('room')]);
        }

        return back();
    }

    /** @return array<string, mixed> */
    private function snapshot(Supply $supply): array
    {
        return [
            'name' => $supply->name,
            'quantity' => $supply->quantity,
            'minimum_level' => $supply->minimum_level,
            'unit' => $supply->unit?->value,
            'room' => $supply->room?->name,
            'storage_location' => $supply->storage_location,
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', Rule::enum(SupplyUnit::class)],
            'quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'minimum_level' => ['required', 'integer', 'min:0', 'max:100000'],
            'room_id' => ['nullable', Rule::exists('rooms', 'id')],
            'storage_location' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
