<?php

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\InspectionResult;
use App\Events\ReportCreated;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\Inspection;
use App\Models\Report;
use App\Models\User;
use App\Services\InspectionRecorder;
use App\Support\EventAction;
use Illuminate\Support\Facades\Event;

function inspectionPayload(array $overrides = []): array
{
    return array_merge([
        'inspector_name' => 'Arben Krasniqi',
        'result' => InspectionResult::Pass->value,
    ], $overrides);
}

it('offers an I-checked-this button on a compliance item, and not on anything else', function () {
    $extinguisher = Asset::factory()->needingInspection()->create();
    $projector = Asset::factory()->create();

    $this->get('/a/'.$extinguisher->asset_tag)->assertOk()->assertSee(__('inspections.log'));
    $this->get('/a/'.$projector->asset_tag)->assertOk()->assertDontSee(__('inspections.log'));
});

it('records an inspection without anybody signing in', function () {
    // Recorded, but from an unknown phone it does not count as the official
    // check: see InspectorPhoneTest.
    $asset = Asset::factory()->needingInspection(12)->create();

    $this->post(route('inspection.store', $asset->asset_tag), inspectionPayload())
        ->assertRedirect(route('scan', $asset->asset_tag))
        ->assertSessionHas('status', __('inspections.pass_not_counted'));

    $inspection = Inspection::query()->firstOrFail();

    expect($inspection->result)->toBe(InspectionResult::Pass)
        ->and($inspection->inspector_name)->toBe('Arben Krasniqi')
        ->and($inspection->asset_id)->toBe($asset->id);
});

it('works out when the next one is due from the interval', function () {
    $asset = Asset::factory()->needingInspection(6)->create();
    asInspectorPhone();

    $this->post(route('inspection.store', $asset->asset_tag), inspectionPayload());

    expect(Inspection::query()->firstOrFail()->next_due_at->toDateString())
        ->toBe(now()->addMonths(6)->toDateString());
});

it('refreshes the cached last-inspected date on the asset', function () {
    $asset = Asset::factory()->needingInspection()->create();
    asInspectorPhone();

    expect($asset->last_inspected_at)->toBeNull();

    $this->post(route('inspection.store', $asset->asset_tag), inspectionPayload());

    expect($asset->fresh()->last_inspected_at)->not->toBeNull();
});

it('writes an inspected event alongside the inspection record', function () {
    $asset = Asset::factory()->needingInspection()->create();

    $this->post(route('inspection.store', $asset->asset_tag), inspectionPayload());

    $event = AssetEvent::query()->where('action', EventAction::ASSET_INSPECTED)->firstOrFail();

    expect($event->meta['result'])->toBe('pass')
        ->and($event->actor_name)->toBe('Arben Krasniqi')
        ->and($event->isSelfReported())->toBeTrue();
});

it('turns a failed inspection into a report in the directors queue', function () {
    // The main reason inspections are a first-class record rather than a log
    // line: a failure has to be actionable (spec section 5).
    Event::fake([ReportCreated::class]);
    $asset = Asset::factory()->needingInspection()->create([
        'status' => AssetStatus::InUse,
        'condition' => AssetCondition::Good,
    ]);

    $this->post(route('inspection.store', $asset->asset_tag), inspectionPayload([
        'result' => InspectionResult::Fail->value,
        'notes' => 'Pressure gauge is in the red.',
    ]))->assertRedirect();

    $asset->refresh();
    $report = Report::query()->firstOrFail();

    expect($asset->condition)->toBe(AssetCondition::NeedsAttention)
        ->and($asset->status)->toBe(AssetStatus::UnderRepair)
        ->and($report->description)->toContain('Pressure gauge is in the red.')
        ->and($report->asset_status_before)->toBe(AssetStatus::InUse)
        ->and($report->asset_condition_before)->toBe(AssetCondition::Good);

    Event::assertDispatched(ReportCreated::class);
});

it('does not queue a second report when something is already open for that item', function () {
    $asset = Asset::factory()->needingInspection()->create();
    Report::factory()->create(['asset_id' => $asset->id]);

    $this->post(route('inspection.store', $asset->asset_tag), inspectionPayload([
        'result' => InspectionResult::Fail->value,
    ]));

    expect(Report::query()->count())->toBe(1)
        ->and($asset->fresh()->condition)->toBe(AssetCondition::NeedsAttention);
});

it('raises no report when the inspection passes', function () {
    $asset = Asset::factory()->needingInspection()->create();

    $this->post(route('inspection.store', $asset->asset_tag), inspectionPayload());

    expect(Report::query()->count())->toBe(0)
        ->and($asset->fresh()->status)->toBe(AssetStatus::InUse);
});

it('needs a name and a result', function () {
    $asset = Asset::factory()->needingInspection()->create();

    $this->post(route('inspection.store', $asset->asset_tag), ['inspector_name' => '', 'result' => ''])
        ->assertSessionHasErrors(['inspector_name', 'result']);
});

it('sends someone scanning a non-compliance item back to the ordinary page', function () {
    $asset = Asset::factory()->create();

    $this->get(route('inspection.create', $asset->asset_tag))
        ->assertRedirect(route('scan', $asset->asset_tag));
});

it('counts anything never inspected as overdue', function () {
    $never = Asset::factory()->needingInspection(12)->create();
    Asset::factory()->create();

    expect(InspectionRecorder::overdue()->pluck('id')->all())->toBe([$never->id]);
});

it('counts an inspection as overdue once its interval has elapsed', function () {
    $asset = Asset::factory()->needingInspection(6)->create();

    $asset->forceFill(['last_inspected_at' => now()->subMonths(7)])->save();
    expect(InspectionRecorder::isOverdue($asset->fresh()))->toBeTrue();

    $asset->forceFill(['last_inspected_at' => now()->subMonths(3)])->save();
    expect(InspectionRecorder::isOverdue($asset->fresh()))->toBeFalse();
});

it('leaves retired compliance items off the overdue list', function () {
    Asset::factory()->needingInspection()->create(['status' => AssetStatus::Retired]);

    expect(InspectionRecorder::overdue())->toBeEmpty();
});

it('shows the overdue list to the director', function () {
    $asset = Asset::factory()->needingInspection()->create(['name' => 'Fire extinguisher, corridor']);

    $this->actingAs(User::factory()->create())
        ->get(route('admin.inspections.overdue'))
        ->assertOk()
        ->assertSee('Fire extinguisher, corridor');
});

it('says so plainly when nothing is overdue', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.inspections.overdue'))
        ->assertOk()
        ->assertSee(__('inspections.nothing_overdue'));
});
