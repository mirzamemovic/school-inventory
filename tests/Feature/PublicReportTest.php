<?php

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\ReporterType;
use App\Enums\ReportStatus;
use App\Events\ReportCreated;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\BlockedDevice;
use App\Models\Report;
use App\Models\Setting;
use App\Support\EventAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

function reportPayload(array $overrides = []): array
{
    return array_merge([
        'reporter_name' => 'Arben Krasniqi',
        'reporter_type' => ReporterType::Staff->value,
        'problem' => 'not_turning_on',
        'description' => 'Nothing happens when you press the button.',
    ], $overrides);
}

it('lets anyone report a problem without signing in', function () {
    $asset = Asset::factory()->create();

    $this->get(route('report.create', $asset->asset_tag))
        ->assertOk()
        ->assertSee(__('reports.who_are_you'));
});

it('records a report and sends the reporter a reference they can check later', function () {
    Event::fake([ReportCreated::class]);
    $asset = Asset::factory()->create();

    $response = $this->post(route('report.store', $asset->asset_tag), reportPayload());

    $stored = Report::query()->firstOrFail();

    $response->assertRedirect(route('report.status', $stored->reference))
        ->assertSessionHas('status', __('reports.thank_you'));

    expect($stored->reporter_name)->toBe('Arben Krasniqi')
        ->and($stored->status)->toBe(ReportStatus::Open)
        ->and($stored->description)->toContain(__('reports.problem.not_turning_on'))
        ->and($stored->description)->toContain('Nothing happens when you press the button.');

    Event::assertDispatched(ReportCreated::class);
});

it('accepts a report from one tap with no typing at all', function () {
    $asset = Asset::factory()->create();

    $this->post(route('report.store', $asset->asset_tag), reportPayload(['description' => null]))
        ->assertRedirect();

    expect(Report::query()->firstOrFail()->description)->toBe(__('reports.problem.not_turning_on'));
});

it('refuses a report with neither a tap nor any words', function () {
    $asset = Asset::factory()->create();

    $this->post(route('report.store', $asset->asset_tag), reportPayload(['problem' => null, 'description' => null]))
        ->assertSessionHasErrors('description');

    expect(Report::query()->count())->toBe(0);
});

it('snapshots where the asset was, then flips it to under repair so the next scanner sees the banner', function () {
    $asset = Asset::factory()->create([
        'status' => AssetStatus::InStorage,
        'condition' => AssetCondition::Good,
    ]);

    $this->post(route('report.store', $asset->asset_tag), reportPayload());

    $stored = Report::query()->firstOrFail();

    expect($stored->asset_status_before)->toBe(AssetStatus::InStorage)
        ->and($stored->asset_condition_before)->toBe(AssetCondition::Good)
        ->and($asset->fresh()->status)->toBe(AssetStatus::UnderRepair);
});

it('never lets the report form set the fields only the director controls', function () {
    // The single most likely real vulnerability in this codebase
    // (spec section 13).
    $asset = Asset::factory()->create();

    $this->post(route('report.store', $asset->asset_tag), reportPayload([
        'status' => ReportStatus::Resolved->value,
        'resolved_at' => now()->toDateTimeString(),
        'resolved_by' => 1,
        'first_viewed_at' => now()->toDateTimeString(),
        'acknowledged_at' => now()->toDateTimeString(),
        'reference' => 'HACKED',
        'asset_id' => 999,
    ]));

    $stored = Report::query()->firstOrFail();

    expect($stored->status)->toBe(ReportStatus::Open)
        ->and($stored->resolved_at)->toBeNull()
        ->and($stored->resolved_by)->toBeNull()
        ->and($stored->first_viewed_at)->toBeNull()
        ->and($stored->acknowledged_at)->toBeNull()
        ->and($stored->reference)->not->toBe('HACKED')
        ->and($stored->asset_id)->toBe($asset->id);
});

it('shows a banner and an add-information button once something is already reported', function () {
    $asset = Asset::factory()->create();
    $this->post(route('report.store', $asset->asset_tag), reportPayload());

    $this->get('/a/'.$asset->asset_tag)
        ->assertOk()
        ->assertSee(__('reports.add_information'))
        ->assertDontSee(__('reports.report_problem'));
});

it('adds to the report that is already open instead of starting a duplicate', function () {
    $asset = Asset::factory()->create();
    $this->post(route('report.store', $asset->asset_tag), reportPayload());

    $this->post(route('report.store', $asset->asset_tag), reportPayload([
        'reporter_name' => 'Drita Berisha',
        'problem' => null,
        'description' => 'The lamp smells burnt.',
    ]));

    expect(Report::query()->count())->toBe(1);

    $stored = Report::query()->firstOrFail();

    expect($stored->description)->toContain('The lamp smells burnt.')
        ->and($stored->description)->toContain('Drita Berisha')
        ->and($stored->reporter_name)->toBe('Arben Krasniqi');
});

it('stops adding to a report that is nearly full instead of failing everyone after', function () {
    // Each addition appends to one TEXT column. Filled to the brim, the next
    // genuine report on this item would fail on MySQL until the director
    // closed it; past the ceiling nothing more is stored and nothing breaks.
    $asset = Asset::factory()->create();
    $report = Report::factory()->create([
        'asset_id' => $asset->id,
        'description' => str_repeat('x', Report::MAX_DESCRIPTION_BYTES - 500),
    ]);

    $this->post(route('report.store', $asset->asset_tag), reportPayload([
        'problem' => null,
        'description' => str_repeat('spam ', 400),
    ]))
        ->assertRedirect(route('report.status', $report->reference))
        ->assertSessionHas('status', __('reports.report_full'));

    expect(strlen($report->fresh()->description))->toBe(Report::MAX_DESCRIPTION_BYTES - 500)
        ->and(Report::query()->count())->toBe(1);
});

it('remembers the name on the device so nobody is asked twice', function () {
    $asset = Asset::factory()->create();
    $other = Asset::factory()->create();

    $response = $this->post(route('report.store', $asset->asset_tag), reportPayload());
    $cookie = $response->getCookie('si_reporter', false);

    expect($cookie)->not->toBeNull();

    $this->withUnencryptedCookie('si_reporter', $cookie->getValue())
        ->get(route('report.create', $other->asset_tag))
        ->assertOk()
        ->assertSee(__('reports.reported_by', ['name' => 'Arben Krasniqi']))
        ->assertDontSee(__('reports.who_are_you'));
});

it('tags a student report so the director can see who sent it', function () {
    $asset = Asset::factory()->create();

    $this->post(route('report.store', $asset->asset_tag), reportPayload([
        'reporter_type' => ReporterType::Student->value,
    ]));

    expect(Report::query()->firstOrFail()->reporter_type)->toBe(ReporterType::Student);
});

it('turns student reporting off with one setting', function () {
    Setting::put('student_reporting_enabled', '0');
    $asset = Asset::factory()->create();

    $this->post(route('report.store', $asset->asset_tag), reportPayload([
        'reporter_type' => ReporterType::Student->value,
    ]))->assertSessionHasErrors('reporter_type');

    expect(Report::query()->count())->toBe(0);
});

it('still lets staff report when student reporting is off', function () {
    Setting::put('student_reporting_enabled', '0');
    $asset = Asset::factory()->create();

    $this->post(route('report.store', $asset->asset_tag), reportPayload())->assertRedirect();

    expect(Report::query()->count())->toBe(1);
});

it('re-encodes an uploaded photo instead of storing what was sent', function () {
    Storage::fake('local');
    $asset = Asset::factory()->create();

    $this->post(route('report.store', $asset->asset_tag), reportPayload([
        'photo' => UploadedFile::fake()->image('evidence.png', 2400, 1600),
    ]))->assertRedirect();

    $path = Report::query()->firstOrFail()->photo_path;

    expect($path)->toStartWith('reports/')
        ->and($path)->toEndWith('.jpg')
        ->and($path)->not->toContain('evidence');

    Storage::disk('local')->assertExists($path);
});

it('refuses a file that is not an image', function () {
    $asset = Asset::factory()->create();

    $this->post(route('report.store', $asset->asset_tag), reportPayload([
        'photo' => UploadedFile::fake()->create('payload.php', 20, 'application/x-php'),
    ]))->assertSessionHasErrors('photo');
});

it('refuses a tiny photo that claims an enormous canvas, before decoding it', function () {
    // About eighty bytes declaring 20,000 × 20,000 pixels. Decoding it would
    // try to allocate well over a gigabyte and take the request down with it;
    // repeated on shared hosting, the whole site.
    $asset = Asset::factory()->create();

    $this->post(route('report.store', $asset->asset_tag), reportPayload([
        'photo' => UploadedFile::fake()->createWithContent('bomb.png', pngDeclaring(20000, 20000)),
    ]))->assertSessionHasErrors('photo');

    expect(Report::query()->count())->toBe(0);
});

it('accepts a normal phone photo of about 8 MB', function () {
    Storage::fake('local');
    $asset = Asset::factory()->create();

    $this->post(route('report.store', $asset->asset_tag), reportPayload([
        'photo' => UploadedFile::fake()->image('phone.jpg', 1200, 900)->size(8 * 1024),
    ]))->assertRedirect()->assertSessionHasNoErrors();

    Storage::disk('local')->assertExists(Report::query()->firstOrFail()->photo_path);
});

it('refuses a photo over 15 MB in plain words', function () {
    Storage::fake('local');
    $asset = Asset::factory()->create();

    $this->post(route('report.store', $asset->asset_tag), reportPayload([
        'photo' => UploadedFile::fake()->image('huge.jpg', 1200, 900)->size(16 * 1024),
    ]))->assertSessionHasErrors(['photo' => __('reports.photo_too_big')]);

    expect(Report::query()->count())->toBe(0);
});

it('writes the report and the status change into the audit log', function () {
    $asset = Asset::factory()->create();

    $this->post(route('report.store', $asset->asset_tag), reportPayload());

    $created = AssetEvent::query()->where('action', EventAction::REPORT_CREATED)->firstOrFail();

    expect($created->subject_type)->toBe('report')
        ->and($created->actor_name)->toBe('Arben Krasniqi')
        ->and($created->isSelfReported())->toBeTrue()
        ->and($created->device_token)->not->toBeNull()
        ->and($created->ip_address)->not->toBeNull();

    expect(AssetEvent::query()->where('action', EventAction::ASSET_STATUS_CHANGED)->exists())->toBeTrue();
});

it('stops a device after three reports in an hour and says so in plain words', function () {
    $assets = Asset::factory()->count(5)->create();

    // Establish the device the way a real phone does, then keep sending its
    // cookie back.
    $first = $this->post(route('report.store', $assets[0]->asset_tag), reportPayload());
    $cookie = $first->getCookie('si_reporter', false)->getValue();

    foreach ([1, 2] as $index) {
        $this->withUnencryptedCookie('si_reporter', $cookie)
            ->post(route('report.store', $assets[$index]->asset_tag), reportPayload())
            ->assertRedirect();
    }

    $this->withUnencryptedCookie('si_reporter', $cookie)
        ->post(route('report.store', $assets[3]->asset_tag), reportPayload())
        ->assertStatus(429)
        ->assertSee(__('reports.too_many'))
        ->assertSee(__('reports.too_many_help'));

    expect(Report::query()->count())->toBe(3);

    RateLimiter::clear('device:'.Report::query()->firstOrFail()->device_token);
});

it('refuses to write anything for a device that has been blocked', function () {
    // The realistic sequence: joke reports arrive, the device token is read off
    // the audit log, and the device is blocked (spec section 13).
    $first = Asset::factory()->create();
    $second = Asset::factory()->create();

    $response = $this->post(route('report.store', $first->asset_tag), reportPayload());
    $cookie = $response->getCookie('si_reporter', false)->getValue();
    $token = Report::query()->firstOrFail()->device_token;

    BlockedDevice::query()->create(['device_token' => $token, 'note' => 'Joke reports']);

    $this->withUnencryptedCookie('si_reporter', $cookie)
        ->post(route('report.store', $second->asset_tag), reportPayload())
        ->assertStatus(429);

    expect(Report::query()->count())->toBe(1);

    RateLimiter::clear('device:'.$token);
});

it('lets a reporter check on their own report with the reference', function () {
    $report = Report::factory()->create();

    $this->get(route('report.status', $report->reference))
        ->assertOk()
        ->assertSee($report->asset->displayName())
        ->assertSee($report->reference)
        ->assertSee(__('reports.status_open'));
});

it('keeps the status page out of search engines and free of anything else', function () {
    $report = Report::factory()->create();
    $other = Report::factory()->create(['description' => 'A completely different fault']);

    $this->get(route('report.status', $report->reference))
        ->assertOk()
        ->assertSee('noindex, nofollow', escape: false)
        ->assertDontSee('A completely different fault');
});
