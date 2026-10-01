<?php

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\ReportStatus;
use App\Events\ReportCreated;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\Person;
use App\Models\Report;
use App\Models\User;
use App\Support\EventAction;
use Illuminate\Support\Facades\Event;

/*
 * Rules the server enforces whatever the page shows: a closed report stays
 * closed, equipment is only made the responsibility of someone still here.
 */

beforeEach(function () {
    $this->admin = User::factory()->create();
});

it('refuses to close a report that is already closed, and leaves the asset alone', function () {
    $asset = Asset::factory()->create(['status' => AssetStatus::InUse, 'condition' => AssetCondition::Good]);

    // Fixed once: the asset goes back into use.
    $first = Report::factory()->create([
        'asset_id' => $asset->id,
        'status' => ReportStatus::Open,
        'asset_status_before' => AssetStatus::InStorage,
        'asset_condition_before' => AssetCondition::Good,
    ]);
    $asset->update(['status' => AssetStatus::UnderRepair]);
    $this->actingAs($this->admin)->post(route('admin.reports.resolve', $first))->assertSessionHasNoErrors();
    $resolvedAt = $first->fresh()->resolved_at;

    // A new fault is reported on the same item.
    expect($asset->refresh()->status)->toBe(AssetStatus::InStorage);
    Report::factory()->create(['asset_id' => $asset->id, 'status' => ReportStatus::Open, 'asset_status_before' => AssetStatus::InStorage]);
    $asset->update(['status' => AssetStatus::UnderRepair]);

    // A stale tab presses "Fixed" on the first report again.
    $this->travel(5)->minutes();
    $this->post(route('admin.reports.resolve', $first))->assertSessionHasErrors(['report' => __('reports.already_closed')]);
    $this->post(route('admin.reports.dismiss', $first))->assertSessionHasErrors('report');

    expect($asset->fresh()->status)->toBe(AssetStatus::UnderRepair)
        ->and($first->fresh()->resolved_at->equalTo($resolvedAt))->toBeTrue()
        ->and($first->fresh()->status)->toBe(ReportStatus::Resolved)
        ->and(AssetEvent::query()->where('action', EventAction::REPORT_RESOLVED)->count())->toBe(1);
});

it('does not reopen a closed report with "Working on it"', function () {
    $report = Report::factory()->create(['status' => ReportStatus::Dismissed, 'resolved_at' => now()]);

    $this->actingAs($this->admin)->post(route('admin.reports.acknowledge', $report))
        ->assertSessionHasErrors(['report' => __('reports.already_closed')]);

    expect($report->fresh()->status)->toBe(ReportStatus::Dismissed)
        ->and(AssetEvent::query()->where('action', EventAction::REPORT_ACKNOWLEDGED)->count())->toBe(0);
});

it('still lets an open or in-progress report be worked on and closed', function () {
    $report = Report::factory()->create(['status' => ReportStatus::Open]);

    $this->actingAs($this->admin)->post(route('admin.reports.acknowledge', $report))->assertSessionHasNoErrors();
    expect($report->fresh()->status)->toBe(ReportStatus::InProgress);

    $this->post(route('admin.reports.resolve', $report))->assertSessionHasNoErrors();
    expect($report->fresh()->status)->toBe(ReportStatus::Resolved);
});

it('announces a new report once it is saved', function () {
    Event::fake([ReportCreated::class]);
    $asset = Asset::factory()->create(['status' => AssetStatus::InUse]);

    $this->post(route('report.store', $asset->asset_tag), [
        'reporter_name' => 'Arben Krasniqi',
        'reporter_type' => 'staff',
        'problem' => 'not_turning_on',
    ])->assertRedirect();

    Event::assertDispatched(ReportCreated::class, 1);
    expect($asset->fresh()->status)->toBe(AssetStatus::UnderRepair);
});

it('makes equipment the responsibility only of someone still at the school', function () {
    $asset = Asset::factory()->create();
    $left = Person::factory()->create(['active' => false]);
    $here = Person::factory()->create(['active' => true]);

    $this->actingAs($this->admin)
        ->post(route('admin.assets.assign', $asset), ['assigned_person_id' => $left->id])
        ->assertSessionHasErrors(['assigned_person_id' => __('assets.assign_inactive')]);

    expect($asset->fresh()->assigned_person_id)->toBeNull();

    $this->post(route('admin.assets.assign', $asset), ['assigned_person_id' => $here->id])->assertSessionHasNoErrors();

    expect($asset->fresh()->assigned_person_id)->toBe($here->id);
});
