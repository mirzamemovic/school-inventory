<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\AuditLogger;
use App\Support\EventAction;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function __construct(private readonly AuditLogger $log) {}

    /** Three tabs, opening on Open, oldest first. */
    public function index(Request $request): View
    {
        $tab = in_array($request->string('tab')->toString(), ['open', 'in_progress', 'closed'], true)
            ? $request->string('tab')->toString()
            : 'open';

        $reports = Report::query()
            ->with('asset.room')
            ->when($tab === 'open', fn ($q) => $q->open())
            ->when($tab === 'in_progress', fn ($q) => $q->inProgress())
            ->when($tab === 'closed', fn ($q) => $q->closed())
            ->when($tab === 'closed', fn ($q) => $q->latest('created_at'), fn ($q) => $q->oldest('created_at'))
            ->paginate(50)
            ->withQueryString();

        return view('admin.reports.index', [
            'reports' => $reports,
            'tab' => $tab,
            'counts' => [
                'open' => Report::query()->open()->count(),
                'in_progress' => Report::query()->inProgress()->count(),
                'closed' => Report::query()->closed()->count(),
            ],
        ]);
    }

    public function show(Report $report): View
    {
        // Stamped on first page load and never overwritten: only the first view
        // counts, because that is the moment the director knew.
        if ($report->first_viewed_at === null) {
            $report->forceFill([
                'first_viewed_at' => now(),
                'first_viewed_by' => auth()->id(),
            ])->save();

            $this->log->record($report, EventAction::REPORT_VIEWED);
        }

        $report->load(['asset.room', 'asset.events']);

        return view('admin.reports.show', [
            'report' => $report,
            // The thing that makes the system feel like it knows something the
            // director does not: seeing that this projector was also repaired
            // in March and November changes it from a one-off into a
            // replacement decision (spec section 7B).
            'history' => $report->asset->reports()
                ->whereKeyNot($report->getKey())
                ->limit(5)
                ->get(),
        ]);
    }

    public function acknowledge(Report $report): RedirectResponse
    {
        $acted = $this->whileStillOpen($report, function (Report $report) {
            $report->forceFill([
                'status' => ReportStatus::InProgress,
                'acknowledged_at' => $report->acknowledged_at ?? now(),
            ])->save();

            $this->log->record($report, EventAction::REPORT_ACKNOWLEDGED);
        });

        return $acted
            ? back()->with('status', __('reports.acknowledged'))
            : back()->withErrors(['report' => __('reports.already_closed')]);
    }

    public function resolve(Request $request, Report $report): RedirectResponse
    {
        return $this->close($request, $report, ReportStatus::Resolved, EventAction::REPORT_RESOLVED, __('reports.resolved'));
    }

    public function dismiss(Request $request, Report $report): RedirectResponse
    {
        return $this->close($request, $report, ReportStatus::Dismissed, EventAction::REPORT_DISMISSED, __('reports.dismissed'));
    }

    private function close(Request $request, Report $report, ReportStatus $status, string $action, string $message): RedirectResponse
    {
        $validated = $request->validate([
            'resolution_note' => ['nullable', 'string', 'max:2000'],
            // The rare override, for a partial repair where the condition
            // should stay as needing attention.
            'status' => ['nullable', Rule::in(collect(AssetStatus::assignable())->map->value)],
            'condition' => ['nullable', Rule::enum(AssetCondition::class)],
        ]);

        $acted = $this->whileStillOpen($report, function (Report $report) use ($status, $action, $validated) {
            $report->forceFill([
                'status' => $status,
                'resolved_at' => now(),
                'resolved_by' => auth()->id(),
                'resolution_note' => $validated['resolution_note'] ?? null,
            ])->save();

            $this->log->record($report, $action, array_filter([
                'note' => $validated['resolution_note'] ?? null,
            ]));

            $this->restoreAsset($report, $validated);
        });

        return $acted
            ? back()->with('status', $message)
            : back()->withErrors(['report' => __('reports.already_closed')]);
    }

    /**
     * The three buttons only act on a report that is still open, checked on
     * the server under a row lock, not only by hiding them on the page.
     * Otherwise a second "Fixed" — from a stale tab, or the other admin at the
     * same moment — would overwrite who closed it and when, and put the
     * asset back to an old snapshot even if a new fault had been reported on
     * it since.
     *
     * @param  Closure(Report): void  $change
     */
    private function whileStillOpen(Report $report, Closure $change): bool
    {
        return DB::transaction(function () use ($report, $change) {
            $report = Report::query()->lockForUpdate()->findOrFail($report->getKey());

            if (! $report->isOpen()) {
                return false;
            }

            $change($report);

            return true;
        });
    }

    /**
     * Put the asset back where it actually was, silently and without asking.
     * A projector that was in storage when it broke goes back into storage, not
     * into service (spec section 7B).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function restoreAsset(Report $report, array $overrides): void
    {
        $asset = $report->asset;

        $status = isset($overrides['status']) ? AssetStatus::from($overrides['status']) : $report->asset_status_before;
        $condition = isset($overrides['condition']) ? AssetCondition::from($overrides['condition']) : $report->asset_condition_before;

        // Only restore if nothing else has moved on since. Newer information
        // beats a stale snapshot.
        if ($asset->status !== AssetStatus::UnderRepair && ! isset($overrides['status'])) {
            return;
        }

        $from = $asset->status->value;

        $asset->update(['status' => $status, 'condition' => $condition]);

        $this->log->record($asset, EventAction::ASSET_STATUS_CHANGED, [
            'status' => ['from' => $from, 'to' => $status->value],
            'reference' => $report->reference,
        ]);
    }
}
