<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssetStatus;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\LabelQueueItem;
use App\Services\AssetTagGenerator;
use App\Services\AuditLogger;
use App\Services\LabelSheet;
use App\Support\EventAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class LabelController extends Controller
{
    public function __construct(
        private readonly LabelSheet $sheets,
        private readonly AuditLogger $log,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.labels.index', [
            'queued' => $this->queue($request)->with('asset')->latest()->get(),
            'perSheet' => $this->sheets->perSheet(),
            'reprintable' => Asset::query()
                ->search($request->string('q'))
                ->orderBy('tag_sequence')
                ->limit(100)
                ->get(),
        ]);
    }

    /** The default action after adding an asset, because printing one label wastes 23. */
    public function addToQueue(Request $request, Asset $asset): RedirectResponse
    {
        LabelQueueItem::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'asset_id' => $asset->id,
        ]);

        return back()->with('status', __('labels.added_to_queue'));
    }

    public function removeFromQueue(Request $request, Asset $asset): RedirectResponse
    {
        $this->queue($request)->where('asset_id', $asset->id)->delete();

        return back()->with('status', __('labels.removed_from_queue'));
    }

    /** Print the pending sheet and clear the queue. */
    public function printQueue(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate($this->startPositionRules());

        $items = $this->queue($request)->with('asset')->oldest()->get();

        if ($items->isEmpty()) {
            return back()->with('status', __('labels.queue_empty'));
        }

        $pdf = $this->sheets->render($items->pluck('asset'), (int) $validated['start_position']);

        $this->queue($request)->delete();

        return $pdf->download('labels-'.now()->format('Y-m-d-Hi').'.pdf');
    }

    /** For when a label is genuinely needed right now. */
    public function printOne(Request $request, Asset $asset): Response
    {
        $validated = $request->validate($this->startPositionRules());

        return $this->sheets
            ->render(collect([$asset]), (int) $validated['start_position'])
            ->download($asset->asset_tag.'.pdf');
    }

    /**
     * A reprint always carries the same code. A new tag would sever the item
     * from its entire history (spec section 6.2).
     */
    public function reprint(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate($this->startPositionRules() + [
            'asset_ids' => ['required', 'array', 'min:1'],
            'asset_ids.*' => ['integer', 'exists:assets,id'],
        ]);

        $assets = Asset::query()->whereIn('id', $validated['asset_ids'])->orderBy('tag_sequence')->get();

        return $this->sheets
            ->render($assets, (int) $validated['start_position'])
            ->download('reprint-'.now()->format('Y-m-d-Hi').'.pdf');
    }

    /**
     * The bulk labelling day: create N rows carrying nothing but a tag, print
     * them, stick them on everything, and fill in the details later.
     */
    public function generateBlanks(Request $request, AssetTagGenerator $tags): Response
    {
        $validated = $request->validate($this->startPositionRules() + [
            'count' => ['required', 'integer', 'min:1', 'max:480'],
        ]);

        $assets = DB::transaction(function () use ($validated, $tags) {
            $created = collect();

            for ($i = 0; $i < (int) $validated['count']; $i++) {
                $created->push($tags->assignAndSave(new Asset(['status' => AssetStatus::Unassigned])));
            }

            return $created;
        });

        $this->log->record($assets->first(), EventAction::ASSET_CREATED, [
            'blank_labels' => count($assets),
            'from' => $assets->first()->asset_tag,
            'to' => $assets->last()->asset_tag,
        ]);

        return $this->sheets
            ->render($assets, (int) $validated['start_position'])
            ->download('blank-labels-'.now()->format('Y-m-d-Hi').'.pdf');
    }

    /** @return array<string, mixed> */
    private function startPositionRules(): array
    {
        return [
            'start_position' => ['required', 'integer', 'min:1', 'max:'.$this->sheets->perSheet()],
        ];
    }

    private function queue(Request $request)
    {
        return LabelQueueItem::query()->where('user_id', $request->user()->id);
    }
}
