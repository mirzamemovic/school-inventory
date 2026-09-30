<?php

use App\Enums\InspectionResult;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\BlockedDevice;
use App\Models\Inspection;
use App\Models\InspectorPhone;
use App\Models\Report;
use App\Models\User;
use App\Services\InspectionRecorder;
use App\Support\EventAction;
use Illuminate\Support\Facades\URL;

/*
| Anyone who scans a fire extinguisher may say what they found, but only a
| check from a phone the director set up in person — or from a signed-in admin —
| moves the inspection date. Anonymous input can raise a warning; it must
| never silence one.
*/

beforeEach(function () {
    $this->admin = User::factory()->create();
});

function setupLinkFor(InspectorPhone $phone): string
{
    return URL::temporarySignedRoute('inspector-phone.setup', $phone->invite_expires_at, ['phone' => $phone]);
}

/* -- which checks count ---------------------------------------------------- */

it('does not let a check from an unknown phone reset the inspection date', function () {
    $asset = Asset::factory()->needingInspection(12)->create();

    $this->post(route('inspection.store', $asset->asset_tag), [
        'inspector_name' => 'Anyone',
        'result' => InspectionResult::Pass->value,
    ])->assertSessionHas('status', __('inspections.pass_not_counted'));

    $inspection = Inspection::query()->firstOrFail();

    expect($inspection->counted)->toBeFalse()
        ->and($inspection->next_due_at)->toBeNull()
        ->and($asset->fresh()->last_inspected_at)->toBeNull()
        ->and(InspectionRecorder::isOverdue($asset->fresh()))->toBeTrue();
});

it('tells the person before they tap that this phone will not count', function () {
    $asset = Asset::factory()->needingInspection()->create();

    $this->get(route('inspection.create', $asset->asset_tag))
        ->assertOk()
        ->assertSee(__('inspections.not_counted_note'));
});

it('counts a check from a phone the director set up', function () {
    $asset = Asset::factory()->needingInspection(12)->create();
    $token = asInspectorPhone();

    $this->get(route('inspection.create', $asset->asset_tag))->assertDontSee(__('inspections.not_counted_note'));

    $this->post(route('inspection.store', $asset->asset_tag), [
        'inspector_name' => 'Caretaker',
        'result' => InspectionResult::Pass->value,
    ])->assertSessionHas('status', __('inspections.pass_thanks'));

    expect(Inspection::query()->firstOrFail()->counted)->toBeTrue()
        ->and($asset->fresh()->last_inspected_at)->not->toBeNull()
        ->and(InspectorPhone::query()->where('device_token', $token)->firstOrFail()->last_used_at)->not->toBeNull();
});

it('counts a check recorded while the director is signed in', function () {
    $asset = Asset::factory()->needingInspection()->create();

    $this->actingAs($this->admin)->post(route('inspection.store', $asset->asset_tag), [
        'inspector_name' => 'Director',
        'result' => InspectionResult::Pass->value,
    ]);

    $event = AssetEvent::query()->where('action', EventAction::ASSET_INSPECTED)->firstOrFail();

    expect($asset->fresh()->last_inspected_at)->not->toBeNull()
        ->and($event->actor_id)->toBe($this->admin->id)
        ->and($event->meta['counted'])->toBeTrue();
});

it('still raises a report when an unknown phone finds a problem, without touching the date', function () {
    // Raising a warning is always allowed; only quietening one needs trust.
    $asset = Asset::factory()->needingInspection()->create();

    $this->post(route('inspection.store', $asset->asset_tag), [
        'inspector_name' => 'Anyone',
        'result' => InspectionResult::Fail->value,
    ]);

    expect(Report::query()->count())->toBe(1)
        ->and($asset->fresh()->last_inspected_at)->toBeNull();
});

it('stops counting a phone the moment it is withdrawn', function () {
    $asset = Asset::factory()->needingInspection()->create();
    asInspectorPhone();
    $phone = InspectorPhone::query()->firstOrFail();

    $this->actingAs($this->admin)
        ->delete(route('admin.inspectors.withdraw', $phone))
        ->assertRedirect(route('admin.inspectors.index'));

    auth()->logout();

    $this->post(route('inspection.store', $asset->asset_tag), [
        'inspector_name' => 'Caretaker',
        'result' => InspectionResult::Pass->value,
    ]);

    expect($asset->fresh()->last_inspected_at)->toBeNull()
        ->and(AssetEvent::query()->where('action', EventAction::INSPECTOR_PHONE_WITHDRAWN)->exists())->toBeTrue();
});

it('shows the director when an uncounted check was made on an overdue item', function () {
    $asset = Asset::factory()->needingInspection()->create(['name' => 'Fire extinguisher, corridor']);
    Inspection::factory()->notCounted()->create(['asset_id' => $asset->id, 'inspector_name' => 'Teuta']);

    $this->actingAs($this->admin)
        ->get(route('admin.inspections.overdue'))
        ->assertOk()
        ->assertSee('Fire extinguisher, corridor')
        ->assertSee('Teuta checked it');
});

/* -- setting a phone up ---------------------------------------------------- */

it('makes a once-only set-up code for a new phone and logs it', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.inspectors.store'), ['label' => "Caretaker's phone"])
        ->assertRedirect();

    $phone = InspectorPhone::query()->firstOrFail();

    expect($phone->isClaimable())->toBeTrue()
        ->and($phone->invite_expires_at->diffInMinutes(now(), true))->toBeLessThanOrEqual(InspectorPhone::INVITE_MINUTES)
        ->and(AssetEvent::query()->where('action', EventAction::INSPECTOR_PHONE_INVITED)->exists())->toBeTrue();

    $this->actingAs($this->admin)
        ->get(route('admin.inspectors.show', $phone))
        ->assertOk()
        ->assertSee('<svg', escape: false)
        ->assertSee('/inspector/'.$phone->id, escape: false);
});

it('does not use up the code when the link is merely opened', function () {
    // Messaging apps fetch a link to draw its preview.
    $phone = InspectorPhone::factory()->create();

    $this->get(setupLinkFor($phone))
        ->assertOk()
        ->assertSee(__('inspections.phones.setup_button'));

    expect($phone->fresh()->claimed_at)->toBeNull();
});

it('sets the phone up when the button is pressed, and remembers the phone', function () {
    $phone = InspectorPhone::factory()->create();

    $response = $this->post(setupLinkFor($phone));

    $response->assertRedirect(route('inspector-phone.done'));

    $phone->refresh();
    $cookie = json_decode($response->getCookie('si_reporter')->getValue(), true);

    expect($phone->isActive())->toBeTrue()
        ->and($phone->device_token)->toBe($cookie['token'])
        ->and(AssetEvent::query()->where('action', EventAction::INSPECTOR_PHONE_CLAIMED)->firstOrFail()->isSelfReported())->toBeTrue();
});

it('lets a set-up code be used only once', function () {
    $phone = InspectorPhone::factory()->create();
    $url = setupLinkFor($phone);

    $this->withCookie('si_reporter', json_encode(['token' => 'first-phone']))->post($url);
    $this->withCookie('si_reporter', json_encode(['token' => 'someone-else']))->post($url)->assertStatus(410);

    expect($phone->fresh()->device_token)->toBe('first-phone');
});

it('refuses a set-up link that has run out, or been tampered with', function () {
    $phone = InspectorPhone::factory()->create();
    $other = InspectorPhone::factory()->create();
    $url = setupLinkFor($phone);

    // The signature covers which phone the code is for.
    $this->get(str_replace('/inspector/'.$phone->id, '/inspector/'.$other->id, $url))->assertForbidden();

    $this->travel(InspectorPhone::INVITE_MINUTES + 1)->minutes();

    $this->get($url)->assertForbidden();
    $this->post($url)->assertForbidden();

    expect($phone->fresh()->claimed_at)->toBeNull();
});

it('will not set up a blocked device', function () {
    BlockedDevice::query()->create(['device_token' => 'spammer']);
    $phone = InspectorPhone::factory()->create();

    $this->withCookie('si_reporter', json_encode(['token' => 'spammer']))
        ->post(setupLinkFor($phone))
        ->assertStatus(429);

    expect($phone->fresh()->claimed_at)->toBeNull();
});

it('keeps the phone list behind the admin login', function () {
    $this->get(route('admin.inspectors.index'))->assertRedirect(route('login'));
    $this->post(route('admin.inspectors.store'), ['label' => 'x'])->assertRedirect(route('login'));
});
