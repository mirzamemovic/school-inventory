<?php

namespace App\Services;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\InspectionResult;
use App\Enums\ReporterType;
use App\Enums\ReportStatus;
use App\Events\ReportCreated;
use App\Models\Asset;
use App\Models\Inspection;
use App\Models\Report;
use App\Support\Actor;
use App\Support\EventAction;
use Illuminate\Support\Facades\DB;

/**
 * Compliance items are an inspection schedule, not inventory. The QR on a fire
 * extinguisher is an "I checked this and it's fine" button (spec section 4).
 */
class InspectionRecorder
{
    public function __construct(private readonly AuditLogger $log) {}

    public function record(
        Asset $asset,
        InspectionResult $result,
        string $inspectorName,
        ?string $notes = null,
        ?Actor $actor = null,
        ?string $deviceToken = null,
    ): Inspection {
        return DB::transaction(function () use ($asset, $result, $inspectorName, $notes, $actor, $deviceToken) {
            $inspectedAt = now();

            // Captured before the failure handling changes anything, so the
            // snapshot on any report raised below is what the asset was
            // actually like when the inspector arrived.
            $statusBefore = $asset->status;
            $conditionBefore = $asset->condition;

            $inspection = Inspection::query()->create([
                'asset_id' => $asset->id,
                'inspected_at' => $inspectedAt,
                'inspector_name' => $inspectorName,
                'result' => $result,
                'notes' => $notes,
                'next_due_at' => $this->nextDue($asset, $inspectedAt),
                'device_token' => $deviceToken,
            ]);

            // Derived, never edited by hand: recalculated only when an
            // inspection is recorded (spec section 5).
            $asset->forceFill(['last_inspected_at' => $inspectedAt])->save();

            $this->log->record($asset, EventAction::ASSET_INSPECTED, [
                'result' => $result->value,
                'inspector' => $inspectorName,
            ], $actor);

            if ($result === InspectionResult::Fail) {
                $this->raiseReportForFailure(
                    $asset, $inspection, $inspectorName, $actor, $deviceToken, $statusBefore, $conditionBefore
                );
            }

            return $inspection;
        });
    }

    /**
     * A failed inspection has to be actionable, which is the main reason
     * inspections are a first-class record: the failure sets the condition and
     * puts the item into the director's queue by itself (spec section 5).
     */
    private function raiseReportForFailure(
        Asset $asset,
        Inspection $inspection,
        string $inspectorName,
        ?Actor $actor,
        ?string $deviceToken,
        AssetStatus $statusBefore,
        AssetCondition $conditionBefore,
    ): void {
        $asset->forceFill(['condition' => AssetCondition::NeedsAttention])->save();

        // One fault, one report: if something is already open for this item,
        // the failure is added to it rather than queued twice.
        if ($asset->openReport() !== null) {
            return;
        }

        $report = new Report;
        $report->reference = Report::newReference();
        $report->asset_id = $asset->id;
        $report->reporter_name = $inspectorName;
        $report->reporter_type = ReporterType::Other;
        $report->device_token = $deviceToken;
        $report->description = __('inspections.failed_report', [
            'notes' => $inspection->notes ?: __('inspections.no_notes'),
        ]);
        $report->status = ReportStatus::Open;
        $report->asset_status_before = $statusBefore;
        $report->asset_condition_before = $conditionBefore;
        $report->save();

        $asset->forceFill(['status' => AssetStatus::UnderRepair])->save();

        $this->log->record($report, EventAction::REPORT_CREATED, [
            'from_inspection' => true,
            'reference' => $report->reference,
        ], $actor);

        ReportCreated::dispatch($report);
    }

    /** Next due is calculated from the newest inspection, never stored on the asset. */
    private function nextDue(Asset $asset, \Illuminate\Support\Carbon $inspectedAt): ?\Illuminate\Support\Carbon
    {
        return $asset->inspection_interval_months
            ? $inspectedAt->copy()->addMonths($asset->inspection_interval_months)
            : null;
    }

    /**
     * Everything whose interval has elapsed, plus anything that has never been
     * inspected at all.
     *
     * Filtered in PHP rather than SQL on purpose: "last_inspected_at plus N
     * months" needs DATE_ADD on MySQL and datetime(...) on SQLite, and at a
     * hundred compliance items the difference is unmeasurable.
     *
     * @return \Illuminate\Support\Collection<int, Asset>
     */
    public static function overdue(): \Illuminate\Support\Collection
    {
        return Asset::query()
            ->with('room')
            ->where('needs_inspection', true)
            ->where('status', '!=', AssetStatus::Retired->value)
            ->orderBy('last_inspected_at')
            ->get()
            ->filter(fn (Asset $asset) => self::isOverdue($asset))
            ->values();
    }

    public static function isOverdue(Asset $asset): bool
    {
        if (! $asset->needs_inspection) {
            return false;
        }

        if ($asset->last_inspected_at === null) {
            return true;
        }

        return self::dueOn($asset)?->isPast() ?? false;
    }

    public static function dueOn(Asset $asset): ?\Illuminate\Support\Carbon
    {
        if ($asset->last_inspected_at === null || ! $asset->inspection_interval_months) {
            return null;
        }

        return $asset->last_inspected_at->copy()->addMonths($asset->inspection_interval_months);
    }
}
