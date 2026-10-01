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
use Illuminate\Support\Facades\DB;
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
        DB::transaction(function () use ($room) {
            $room->update(['last_counted_at' => now()]);

            $this->log->record($room, EventAction::ROOM_COUNTED, ['name' => $room->name]);
        });

        return back()->with('status', __('rooms.marked_counted'));
    }

    public function storeContent(Request $request, Room $room): RedirectResponse
    {
        $validated = $request->validate([
            'item_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'condition' => ['required', new Enum(AssetCondition::class)],
        ]);

        DB::transaction(function () use ($room, $validated) {
            $content = $room->contents()->create($validated);

            // Furniture has no history of its own; it is counted per room.
            $this->log->record($room, EventAction::ROOM_FURNITURE_ADDED, [
                'name' => $room->name,
                'item' => $content->item_name,
                'quantity' => $content->quantity,
                'condition' => $content->condition?->value,
            ]);
        });

        return back()->with('status', __('rooms.furniture_added'));
    }

    /**
     * The route binds the furniture line within this room only, so a changed
     * address cannot alter another room's furniture under this room's name.
     */
    public function updateContent(Request $request, Room $room, RoomContent $content): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        $before = $content->quantity;
        $after = (int) $validated['quantity'];

        DB::transaction(function () use ($room, $content, $before, $after) {
            if ($after === 0) {
                $content->delete();
            } else {
                $content->update(['quantity' => $after]);
            }

            if ($before !== $after) {
                $this->log->record($room, EventAction::ROOM_FURNITURE_CHANGED, [
                    'quantity' => ['from' => $before, 'to' => $after],
                    'name' => $room->name,
                    'item' => $content->item_name,
                ]);
            }
        });

        return back()->with('status', __($after === 0 ? 'rooms.furniture_removed' : 'rooms.changes_saved'));
    }

    public function create(): View
    {
        return view('admin.rooms.create', ['room' => new Room(['type' => RoomType::Classroom])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated) {
            $room = Room::query()->create($validated);

            $this->log->record($room, EventAction::ROOM_CREATED, [
                'name' => $room->name,
                'type' => $room->type?->value,
            ]);
        });

        return redirect()->route('admin.rooms.index')->with('status', __('rooms.added'));
    }

    public function edit(Room $room): View
    {
        return view('admin.rooms.edit', ['room' => $room]);
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($room, $validated) {
            $before = $this->snapshot($room);
            $room->update($validated);

            $this->log->recordChanges($room, EventAction::ROOM_EDITED, $before, $this->snapshot($room), $room->name);
        });

        return redirect()->route('admin.rooms.index')->with('status', __('rooms.changes_saved'));
    }

    /** @return array<string, mixed> */
    private function snapshot(Room $room): array
    {
        return [
            'name' => $room->name,
            'floor' => $room->floor,
            'type' => $room->type?->value,
        ];
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
