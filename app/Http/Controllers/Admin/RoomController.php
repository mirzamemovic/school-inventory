<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssetCondition;
use App\Enums\RoomType;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomContent;
use App\Services\AuditLogger;
use App\Support\EventAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class RoomController extends Controller
{
    public function __construct(private readonly AuditLogger $log) {}

    public function index(Request $request): View
    {
        return view('admin.rooms.index', [
            'rooms' => Room::query()
                ->withCount(['assets', 'contents'])
                ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
                ->orderBy('name')
                ->get(),
        ]);
    }

    /** What is in this room: labelled assets, counted furniture, and supplies. */
    public function show(Room $room): View
    {
        return view('admin.rooms.show', [
            'room' => $room,
            'assets' => $room->assets()->with('category')->orderBy('name')->get(),
            'contents' => $room->contents()->orderBy('item_name')->get(),
            'supplies' => $room->supplies()->orderBy('name')->get(),
        ]);
    }

    /**
     * Schools do the annual physical count on paper, so someone will ask for
     * this (spec section 8).
     */
    public function inventorySheet(Room $room): View
    {
        return view('admin.rooms.sheet', [
            'room' => $room,
            'assets' => $room->assets()->orderBy('name')->get(),
            'contents' => $room->contents()->orderBy('item_name')->get(),
        ]);
    }

    public function markCounted(Room $room): RedirectResponse
    {
        $room->update(['last_counted_at' => now()]);

        return back()->with('status', __('rooms.marked_counted'));
    }

    public function storeContent(Request $request, Room $room): RedirectResponse
    {
        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'condition' => ['required', new Enum(AssetCondition::class)],
        ]);

        $room->contents()->create($validated);

        return back()->with('status', __('rooms.furniture_added'));
    }

    public function updateContent(Request $request, Room $room, RoomContent $content): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        if ((int) $validated['quantity'] === 0) {
            $content->delete();

            return back()->with('status', __('rooms.furniture_removed'));
        }

        $content->update($validated);

        return back()->with('status', __('rooms.changes_saved'));
    }

    public function create(): View
    {
        return view('admin.rooms.create', ['room' => new Room(['type' => RoomType::Classroom])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Room::query()->create($this->validated($request));

        return redirect()->route('admin.rooms.index')->with('status', __('rooms.added'));
    }

    public function edit(Room $room): View
    {
        return view('admin.rooms.edit', ['room' => $room]);
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $room->update($this->validated($request));

        return redirect()->route('admin.rooms.index')->with('status', __('rooms.changes_saved'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:255'],
            'type' => ['required', new Enum(RoomType::class)],
        ]);
    }
}
