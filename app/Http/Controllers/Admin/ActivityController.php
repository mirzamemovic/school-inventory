<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssetEvent;
use App\Models\BlockedDevice;
use App\Services\AuditLogger;
use App\Support\CsvCell;
use App\Support\EventAction;
use App\Support\EventSentence;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The audit log, read only, newest first. Each row reads as a sentence rather
 * than a table of ids (spec section 8).
 */
class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $events = $this->filtered($request)->paginate(50)->withQueryString();

        // Infinite scroll: HTMX asks for the next page when the last row of
        // this one is revealed, and appends it.
        if ($request->header('HX-Request')) {
            return view('admin.activity._rows', ['events' => $events]);
        }

        return view('admin.activity.index', [
            'events' => $events,
            'actions' => EventAction::all(),
        ]);
    }

    /**
     * The log names people, so exporting it is itself a processing event: it is
     * recorded (who, which dataset, how many rows — never the contents) and the
     * file says what it is at the bottom (spec section 16).
     */
    public function export(Request $request, AuditLogger $log): StreamedResponse
    {
        $events = $this->filtered($request)->limit(20000)->get();
        $filename = 'activity-'.now()->format('Y-m-d').'.csv';
        $user = $request->user();

        $log->record($user, EventAction::DATA_EXPORTED, [
            'dataset' => 'activity',
            'rows' => $events->count(),
        ]);

        return response()->streamDownload(function () use ($events, $user) {
            $out = fopen('php://output', 'w');

            fputcsv($out, CsvCell::row([
                __('activity.csv.when'),
                __('activity.csv.what'),
                __('activity.csv.action'),
                __('activity.csv.who'),
                __('activity.csv.verified'),
                __('activity.csv.subject'),
                __('activity.csv.details'),
            ]));

            // Every cell goes through CsvCell: the names in here were typed by
            // anyone on a public page, and this file is opened in Excel.
            foreach ($events as $event) {
                fputcsv($out, CsvCell::row([
                    $event->created_at->toDateTimeString(),
                    EventSentence::for($event),
                    $event->action,
                    $event->actorLabel(),
                    $event->isSelfReported() ? __('common.no') : __('common.yes'),
                    $event->subject_type.'#'.$event->subject_id,
                    json_encode($event->meta),
                ]));
            }

            fputcsv($out, []);
            fputcsv($out, CsvCell::row([__('activity.csv.footer', [
                'date' => now()->format('j F Y, H:i'),
                'name' => $user->name,
            ])]));

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The correct response to the most likely attack: a device is identified
     * from this log and blocked (spec section 13).
     */
    public function blockDevice(Request $request, AuditLogger $log): RedirectResponse
    {
        $validated = $request->validate([
            'device_token' => ['required', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $device = BlockedDevice::query()->firstOrCreate(
            ['device_token' => $validated['device_token']],
            ['note' => $validated['note'] ?? null],
        );

        if ($device->wasRecentlyCreated) {
            $log->record($request->user(), EventAction::DEVICE_BLOCKED, ['device_token' => $device->device_token]);
        }

        return back()->with('status', __('activity.device_blocked'));
    }

    public function unblockDevice(Request $request, BlockedDevice $device, AuditLogger $log): RedirectResponse
    {
        $device->delete();

        $log->record($request->user(), EventAction::DEVICE_UNBLOCKED, ['device_token' => $device->device_token]);

        return back()->with('status', __('activity.device_unblocked'));
    }

    /** @return Builder<AssetEvent> */
    private function filtered(Request $request): Builder
    {
        return AssetEvent::query()
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->when($request->filled('who'), fn ($q) => $q->where('actor_name', 'like', '%'.$request->string('who').'%'))
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                $term = '%'.$request->string('q').'%';

                $q->where(fn (Builder $inner) => $inner
                    ->where('actor_name', 'like', $term)
                    ->orWhere('action', 'like', $term)
                    ->orWhere('meta', 'like', $term));
            })
            ->latest('created_at')
            ->latest('id');
    }
}
