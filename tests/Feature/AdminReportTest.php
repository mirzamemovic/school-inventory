<?php

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\ReportStatus;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\Report;
use App\Models\User;
use App\Support\EventAction;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

it('keeps the queue behind a password', function () {
    $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
});

it('opens on the open tab, oldest first', function () {
    $newer = Report::factory()->reportedDaysAgo(1)->create();
    $older = Report::factory()->reportedDaysAgo(9)->create();
    Report::factory()->resolved()->create();

    $response = $this->actingAs($this->admin)->get(route('admin.reports.index'))->assertOk();

    $reports = $response->viewData('reports');

    expect($reports->pluck('id')->all())->toBe([$older->id, $newer->id]);
});

it('separates open, in progress and closed', function () {
    Report::factory()->create();
    Report::factory()->inProgress()->create();
    Report::factory()->resolved()->create();

    $counts = $this->actingAs($this->admin)
        ->get(route('admin.reports.index'))
        ->viewData('counts');

    expect($counts)->toBe(['open' => 1, 'in_progress' => 1, 'closed' => 1]);
});

it('shows the red count on every screen', function () {
    Report::factory()->count(2)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.assets.index'))
        ->assertOk()
        ->assertSee('bg-status-broken', escape: false)
        ->assertSee(trans_choice('nav.open_reports', 2, ['count' => 2]));
});

it('stamps the moment the director first opened a report and never moves it again', function () {
    $report = Report::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.reports.show', $report))->assertOk();

    $firstView = $report->fresh()->first_viewed_at;

    expect($firstView)->not->toBeNull()
        ->and($report->fresh()->first_viewed_by)->toBe($this->admin->id);

    $this->travel(2)->hours();
    $this->actingAs($this->admin)->get(route('admin.reports.show', $report));

    expect($report->fresh()->first_viewed_at->toDateTimeString())->toBe($firstView->toDateTimeString());
});

it('logs the first view once, not on every visit', function () {
    $report = Report::factory()->create();

    $this->actingAs($this->admin)->get(route('admin.reports.show', $report));
    $this->actingAs($this->admin)->get(route('admin.reports.show', $report));

    expect(AssetEvent::query()->where('action', EventAction::REPORT_VIEWED)->count())->toBe(1);
});

it('shows the item history on the same page so a pattern is visible', function () {
    $asset = Asset::factory()->create();
    Report::factory()->resolved()->create([
        'asset_id' => $asset->id,
        'description' => 'Lamp replaced in March',
    ]);
    $current = Report::factory()->create(['asset_id' => $asset->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.reports.show', $current))
        ->assertOk()
        ->assertSee('Lamp replaced in March');
});

it('marks a report as being worked on', function () {
    $report = Report::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.reports.acknowledge', $report))
        ->assertRedirect();

    $report->refresh();

    expect($report->status)->toBe(ReportStatus::InProgress)
        ->and($report->acknowledged_at)->not->toBeNull()
        ->and(AssetEvent::query()->where('action', EventAction::REPORT_ACKNOWLEDGED)->exists())->toBeTrue();
});

it('puts the item back exactly where it was, without asking', function () {
    // A projector that was in storage when it broke goes back into storage,
    // not into service (spec section 7B).
    $asset = Asset::factory()->create([
        'status' => AssetStatus::UnderRepair,
        'condition' => AssetCondition::Broken,
    ]);
    $report = Report::factory()->create([
        'asset_id' => $asset->id,
        'asset_status_before' => AssetStatus::InStorage,
        'asset_condition_before' => AssetCondition::Good,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.reports.resolve', $report), ['resolution_note' => 'New lamp fitted'])
        ->assertRedirect();

    $asset->refresh();

    expect($asset->status)->toBe(AssetStatus::InStorage)
        ->and($asset->condition)->toBe(AssetCondition::Good)
        ->and($report->fresh()->resolution_note)->toBe('New lamp fitted')
        ->and($report->fresh()->resolved_by)->toBe($this->admin->id);
});

it('does the same when a report is closed as not a problem', function () {
    $asset = Asset::factory()->create(['status' => AssetStatus::UnderRepair]);
    $report = Report::factory()->create([
        'asset_id' => $asset->id,
        'asset_status_before' => AssetStatus::InUse,
        'asset_condition_before' => AssetCondition::Good,
    ]);

    $this->actingAs($this->admin)->post(route('admin.reports.dismiss', $report));

    expect($asset->fresh()->status)->toBe(AssetStatus::InUse)
        ->and($report->fresh()->status)->toBe(ReportStatus::Dismissed)
        ->and(AssetEvent::query()->where('action', EventAction::REPORT_DISMISSED)->exists())->toBeTrue();
});

it('keeps the note when a report is closed as not a problem', function () {
    // One note box sits above both closing buttons, so the page must send it
    // with whichever is pressed — not only with Fixed.
    $report = Report::factory()->create();

    $html = $this->actingAs($this->admin)->get(route('admin.reports.show', $report))->assertOk()->getContent();

    preg_match('#<form method="POST" action="'.preg_quote(route('admin.reports.resolve', $report), '#').'".*?</form>#s', $html, $form);

    expect($form[0] ?? '')
        ->toContain('name="resolution_note"')
        ->toContain('formaction="'.route('admin.reports.dismiss', $report).'"');

    $this->actingAs($this->admin)->post(route('admin.reports.dismiss', $report), [
        'resolution_note' => 'Cable was unplugged, nothing broken.',
    ]);

    expect($report->fresh()->status)->toBe(ReportStatus::Dismissed)
        ->and($report->fresh()->resolution_note)->toBe('Cable was unplugged, nothing broken.');
});

it('leaves a newer decision alone rather than clobbering it with a stale snapshot', function () {
    // Something else moved the asset on during the repair, so the snapshot is
    // no longer the best information available (spec section 7B).
    $asset = Asset::factory()->create(['status' => AssetStatus::Retired]);
    $report = Report::factory()->create([
        'asset_id' => $asset->id,
        'asset_status_before' => AssetStatus::InUse,
        'asset_condition_before' => AssetCondition::Good,
    ]);

    $this->actingAs($this->admin)->post(route('admin.reports.resolve', $report));

    expect($asset->fresh()->status)->toBe(AssetStatus::Retired);
});

it('lets the director set a different state for a partial repair', function () {
    $asset = Asset::factory()->create(['status' => AssetStatus::UnderRepair]);
    $report = Report::factory()->create([
        'asset_id' => $asset->id,
        'asset_status_before' => AssetStatus::InUse,
        'asset_condition_before' => AssetCondition::Good,
    ]);

    $this->actingAs($this->admin)->post(route('admin.reports.resolve', $report), [
        'status' => AssetStatus::InUse->value,
        'condition' => AssetCondition::NeedsAttention->value,
    ]);

    $asset->refresh();

    expect($asset->status)->toBe(AssetStatus::InUse)
        ->and($asset->condition)->toBe(AssetCondition::NeedsAttention);
});

it('never renders a reporter description as markup', function () {
    // That text comes from an anonymous stranger and renders on the director's
    // screen. Stored XSS lives exactly there (spec section 13).
    $report = Report::factory()->create([
        'description' => '<script>alert("xss")</script>',
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.reports.show', $report))
        ->assertOk()
        ->assertDontSee('<script>alert', escape: false)
        ->assertSee('&lt;script&gt;', escape: false);
});
