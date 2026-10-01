<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\ReporterType;
use App\Enums\ReportStatus;
use App\Events\ReportCreated;
use App\Http\Requests\ReportRequest;
use App\Models\Asset;
use App\Models\BlockedDevice;
use App\Models\Report;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\DeviceIdentity;
use App\Services\PhotoStorage;
use App\Support\Actor;
use App\Support\EventAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PublicReportController extends Controller
{
    public function __construct(
        private readonly DeviceIdentity $device,
        private readonly AuditLogger $log,
        private readonly PhotoStorage $photos,
    ) {}

    public function create(Request $request, string $tag): View|Response
    {
        $asset = $this->findAsset($tag);

        if (! $asset instanceof Asset) {
            return $asset;
        }

        $openReport = $asset->openReport();

        return view('public.report', [
            'tag' => $asset->asset_tag,
            'name' => $asset->displayName(),
            'room' => $asset->room?->name,
            // Only asked on the first visit; after that the device remembers.
            'knownName' => $this->device->name($request),
            'knownType' => $this->device->type($request),
            'isAddition' => $openReport !== null,
            'openReportAge' => $openReport?->created_at->diffForHumans(),
            'studentsAllowed' => Setting::bool('student_reporting_enabled', true),
        ]);
    }

    public function store(ReportRequest $request, string $tag): RedirectResponse|Response
    {
        $asset = $this->findAsset($tag);

        if (! $asset instanceof Asset) {
            return $asset;
        }

        $token = $this->device->token($request);

        // A device identified from the audit log and blocked is told nothing
        // useful, and nothing is written.
        if (BlockedDevice::blocks($token)) {
            return response()->view('public.too-many', status: 429);
        }

        $type = ReporterType::from($request->string('reporter_type')->toString());
        $name = $request->string('reporter_name')->trim()->toString();

        $this->device->remember($token, $name, $type);

        // The asset row is locked while deciding whether a report is already
        // open, so two people reporting the same projector at the same moment
        // make one report with an addition, not two whose snapshots disagree.
        return DB::transaction(function () use ($request, $asset, $name, $type, $token) {
            $asset = Asset::query()->with('room')->lockForUpdate()->findOrFail($asset->id);
            $openReport = $asset->openReport();

            return $openReport
                ? $this->addToExistingReport($request, $openReport, $name, $type, $token)
                : $this->createReport($request, $asset, $name, $type, $token);
        });
    }

    /**
     * Someone can check on their own report without an account. The reference
     * is the whole capability, so this page shows nothing but the state of that
     * one report.
     */
    public function show(string $reference): View|Response
    {
        $report = Report::query()->with('asset')->where('reference', $reference)->first();

        if (! $report) {
            return response()->view('public.not-found', ['tag' => $reference], 404);
        }

        return view('public.report-status', [
            'report' => $report,
            'assetName' => $report->asset->displayName(),
        ]);
    }

    private function createReport(ReportRequest $request, Asset $asset, string $name, ReporterType $type, string $token): RedirectResponse
    {
        // Built field by field. Nothing from the request can reach status,
        // resolved_at, resolved_by, first_viewed_at or acknowledged_at
        // (spec section 13).
        $report = new Report;
        $report->reference = Report::newReference();
        $report->asset_id = $asset->id;
        $report->reporter_name = $name;
        $report->reporter_type = $type;
        $report->device_token = $token;
        $report->ip_address = $request->ip();
        $report->description = $request->describedProblem();
        $report->status = ReportStatus::Open;

        // Snapshot where the asset actually was, so resolving it later can put
        // it back rather than guessing (spec section 7B).
        $report->asset_status_before = $asset->status;
        $report->asset_condition_before = $asset->condition;

        if ($request->hasFile('photo')) {
            $report->photo_path = $this->photos->store($request->file('photo'), 'reports');
        }

        $report->save();

        // Now the next person to scan the sticker sees the banner instead of
        // filing the same fault again.
        $asset->update(['status' => AssetStatus::UnderRepair]);

        $actor = new Actor($type->actorType(), null, $name, $token);

        $this->log->record($report, EventAction::REPORT_CREATED, [
            'asset_tag' => $asset->asset_tag,
            'reference' => $report->reference,
        ], $actor);

        $this->log->record($asset, EventAction::ASSET_STATUS_CHANGED, [
            'status' => ['from' => $report->asset_status_before->value, 'to' => AssetStatus::UnderRepair->value],
            'reference' => $report->reference,
        ], $actor);

        // Only once the report is really saved: a future email listener must
        // never announce a report that was rolled back.
        DB::afterCommit(fn () => ReportCreated::dispatch($report));

        return redirect()
            ->route('report.status', $report->reference)
            ->with('status', __('reports.thank_you'));
    }

    /**
     * One fault, one report. A second person scanning the same sticker adds to
     * what is already open rather than starting a duplicate (spec section 7A).
     */
    private function addToExistingReport(ReportRequest $request, Report $report, string $name, ReporterType $type, string $token): RedirectResponse
    {
        $description = trim($report->description)
            ."\n\n"
            .__('reports.addition_by', ['name' => $name, 'time' => now()->format('j M Y, H:i')])
            ."\n"
            .$request->describedProblem();

        // Additions append to one column. Without a ceiling, a handful of
        // long additions fill it, and from then on every genuine report on
        // this item fails outright until the director closes it. Past the
        // ceiling nothing more is stored, and the person is told plainly.
        if (strlen($description) > Report::MAX_DESCRIPTION_BYTES) {
            return redirect()
                ->route('report.status', $report->reference)
                ->with('status', __('reports.report_full'));
        }

        $report->description = $description;

        if ($request->hasFile('photo') && ! $report->photo_path) {
            $report->photo_path = $this->photos->store($request->file('photo'), 'reports');
        }

        $report->save();

        $this->log->record($report, EventAction::REPORT_CREATED, [
            'addition' => true,
            'reference' => $report->reference,
        ], new Actor($type->actorType(), null, $name, $token));

        return redirect()
            ->route('report.status', $report->reference)
            ->with('status', __('reports.thank_you_addition'));
    }

    private function findAsset(string $tag): Asset|Response
    {
        $asset = Asset::query()->with('room')->where('asset_tag', $tag)->first();

        return $asset ?? response()->view('public.not-found', ['tag' => $tag], 404);
    }
}
