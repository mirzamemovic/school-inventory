<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssetRequest;
use App\Models\Asset;
use App\Models\Category;
use App\Models\LabelQueueItem;
use App\Models\Room;
use App\Services\AssetTagGenerator;
use App\Services\AuditLogger;
use App\Services\PhotoStorage;
use App\Support\EventAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function __construct(
        private readonly AuditLogger $log,
        private readonly PhotoStorage $photos,
    ) {}

    public function index(Request $request): View
    {
        $assets = Asset::query()
            ->with(['room', 'category'])
            ->search($request->string('q'))
            ->when($request->filled('room'), fn ($q) => $q->where('room_id', $request->integer('room')))
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->integer('category')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByRaw('(select name from rooms where rooms.id = assets.room_id) is null')
            ->orderByRaw('(select name from rooms where rooms.id = assets.room_id)')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('admin.assets.index', [
            'assets' => $assets,
            'rooms' => Room::query()->orderBy('name')->get(),
            'categories' => Category::query()->orderBy('name')->get(),
            'queueCount' => $this->queueCount($request),
        ]);
    }

    public function create(): View
    {
        return view('admin.assets.create', $this->formData(new Asset(['funding_source' => 'unknown'])));
    }

    public function store(AssetRequest $request, AssetTagGenerator $tags): RedirectResponse
    {
        $asset = new Asset($request->safe()->except('photo'));

        if ($request->hasFile('photo')) {
            $asset->photo_path = $this->photos->store($request->file('photo'), 'assets');
        }

        $tags->assignAndSave($asset);

        $this->log->record($asset, EventAction::ASSET_CREATED, [
            'name' => $asset->name,
            'room' => $asset->room?->name,
            'status' => $asset->status->value,
        ]);

        return redirect()
            ->route('admin.assets.show', $asset)
            ->with('status', __('assets.saved', ['tag' => $asset->asset_tag]));
    }

    public function show(Asset $asset): View
    {
        $asset->load(['room', 'category', 'events', 'assignedPerson', 'inspections']);

        return view('admin.assets.show', [
            'asset' => $asset,
            'inQueue' => LabelQueueItem::query()
                ->where('user_id', auth()->id())
                ->where('asset_id', $asset->id)
                ->exists(),
        ]);
    }

    public function edit(Asset $asset): View
    {
        return view('admin.assets.edit', $this->formData($asset));
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $before = $this->snapshot($asset);

        $asset->fill($request->safe()->except('photo'));

        if ($request->hasFile('photo')) {
            $this->photos->delete($asset->photo_path);
            $asset->photo_path = $this->photos->store($request->file('photo'), 'assets');
        }

        $asset->save();
        $asset->refresh()->load('room');

        $this->recordChanges($asset, $before);

        return redirect()
            ->route('admin.assets.show', $asset)
            ->with('status', __('assets.changes_saved'));
    }

    /**
     * A change of room is a move, a change of state is a status change, and
     * everything else is an edit. Splitting them keeps the timeline readable.
     *
     * @param  array<string, mixed>  $before
     */
    private function recordChanges(Asset $asset, array $before): void
    {
        $after = $this->snapshot($asset);
        $changes = $this->log->changes($before, $after);

        if (isset($changes['room'])) {
            $this->log->record($asset, EventAction::ASSET_MOVED, ['room' => $changes['room']]);
            unset($changes['room']);
        }

        if (isset($changes['status'])) {
            $this->log->record($asset, EventAction::ASSET_STATUS_CHANGED, ['status' => $changes['status']]);
            unset($changes['status']);
        }

        if ($changes !== []) {
            $this->log->record($asset, EventAction::ASSET_EDITED, $changes);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(Asset $asset): array
    {
        return [
            'name' => $asset->name,
            'room' => $asset->room?->name,
            'category' => $asset->category?->name,
            'status' => $asset->status?->value,
            'condition' => $asset->condition?->value,
            'make' => $asset->make,
            'model' => $asset->model,
            'serial_number' => $asset->serial_number,
            'official_number' => $asset->official_number,
            'notes' => $asset->notes,
        ];
    }

    /** @return array<string, mixed> */
    private function formData(Asset $asset): array
    {
        return [
            'asset' => $asset,
            'rooms' => Room::query()->orderBy('name')->get(),
            'categories' => Category::query()->orderBy('name')->get(),
        ];
    }

    private function queueCount(Request $request): int
    {
        return LabelQueueItem::query()->where('user_id', $request->user()->id)->count();
    }
}
