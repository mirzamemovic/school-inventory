<?php

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Report;
use App\Models\User;
use App\Services\NeedsAttention;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

it('says nothing needs attention when nothing does', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.home'))
        ->assertOk()
        ->assertSee(__('home.nothing_needs_attention'));
});

it('raises a report that has been open for more than a week', function () {
    $report = Report::factory()->reportedDaysAgo(9)->create();

    $rows = app(NeedsAttention::class)->rows();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['label'])->toContain($report->asset->displayName())
        ->and($rows->first()['severity'])->toBe('broken');
});

it('leaves a fresh report alone', function () {
    Report::factory()->reportedDaysAgo(2)->create();

    expect(app(NeedsAttention::class)->rows())->toBeEmpty();
});

it('raises an asset that has been under repair for over a month', function () {
    $asset = Asset::factory()->underRepair()->create(['name' => 'Stuck projector']);
    $asset->forceFill(['updated_at' => now()->subDays(40)])->saveQuietly();

    expect(app(NeedsAttention::class)->rows()->first()['label'])->toContain('Stuck projector');
});

it('raises a warranty that runs out within two months', function () {
    Asset::factory()->create(['name' => 'New laptop', 'warranty_end' => now()->addDays(30)]);

    expect(app(NeedsAttention::class)->rows()->first()['label'])->toContain('New laptop');
});

it('ignores a warranty that has months left', function () {
    Asset::factory()->create(['warranty_end' => now()->addDays(200)]);

    expect(app(NeedsAttention::class)->rows())->toBeEmpty();
});

it('ignores the warranty on something already retired', function () {
    Asset::factory()->create([
        'warranty_end' => now()->addDays(10),
        'status' => AssetStatus::Retired,
    ]);

    expect(app(NeedsAttention::class)->rows())->toBeEmpty();
});

it('never shows more than five rows', function () {
    Report::factory()->count(8)->reportedDaysAgo(20)->create();

    expect(app(NeedsAttention::class)->rows())->toHaveCount(5);
});

it('shows the open report count at the top of home', function () {
    Report::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.home'))
        ->assertOk()
        ->assertSee(trans_choice('home.open_reports', 3, ['count' => 3]));
});

it('counts assets without counting unused blank labels', function () {
    Asset::factory()->count(2)->create();
    Asset::factory()->blank()->count(7)->create();

    $totals = $this->actingAs($this->admin)->get(route('admin.home'))->viewData('totals');

    expect($totals['all'])->toBe(2);
});
