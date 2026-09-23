<?php

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\LabelQueueItem;
use App\Models\User;
use App\Services\LabelSheet;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

it('lays 24 labels out on one A4 sheet', function () {
    expect(app(LabelSheet::class)->perSheet())->toBe(24);
});

it('leaves the cells before the chosen start position empty so a part-used sheet can be reused', function () {
    $assets = Asset::factory()->count(3)->create();

    $pages = app(LabelSheet::class)->paginate($assets, startPosition: 5);

    expect($pages)->toHaveCount(1)
        ->and($pages[0])->toHaveCount(24)
        ->and(array_slice($pages[0], 0, 4))->each->toBeNull()
        ->and($pages[0][4]['tag'])->toBe($assets[0]->asset_tag)
        ->and($pages[0][6]['tag'])->toBe($assets[2]->asset_tag)
        ->and($pages[0][7])->toBeNull();
});

it('spills onto a second sheet when the start position pushes labels past the end', function () {
    $assets = Asset::factory()->count(4)->create();

    $pages = app(LabelSheet::class)->paginate($assets, startPosition: 23);

    expect($pages)->toHaveCount(2)
        ->and($pages[0][22]['tag'])->toBe($assets[0]->asset_tag)
        ->and($pages[1][0]['tag'])->toBe($assets[2]->asset_tag);
});

it('starts at the first cell by default', function () {
    $assets = Asset::factory()->count(1)->create();

    expect(app(LabelSheet::class)->paginate($assets)[0][0]['tag'])->toBe($assets[0]->asset_tag);
});

it('adds a label to the queue rather than printing one sticker and wasting 23', function () {
    $asset = Asset::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.labels.queue.add', $asset))
        ->assertRedirect();

    expect(LabelQueueItem::query()->where('asset_id', $asset->id)->exists())->toBeTrue();
});

it('does not queue the same label twice', function () {
    $asset = Asset::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.labels.queue.add', $asset));
    $this->actingAs($this->admin)->post(route('admin.labels.queue.add', $asset));

    expect(LabelQueueItem::query()->count())->toBe(1);
});

it('takes a label back out of the queue', function () {
    $asset = Asset::factory()->create();
    $this->actingAs($this->admin)->post(route('admin.labels.queue.add', $asset));

    $this->actingAs($this->admin)
        ->delete(route('admin.labels.queue.remove', $asset))
        ->assertRedirect();

    expect(LabelQueueItem::query()->count())->toBe(0);
});

it('keeps one persons queue separate from anothers', function () {
    $asset = Asset::factory()->create();
    $secretary = User::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.labels.queue.add', $asset));

    $this->actingAs($secretary)
        ->get(route('admin.labels.index'))
        ->assertOk()
        ->assertSee(__('labels.queue_empty'));
});

it('prints the queue as a PDF and empties it', function () {
    $asset = Asset::factory()->create();
    $this->actingAs($this->admin)->post(route('admin.labels.queue.add', $asset));

    $response = $this->actingAs($this->admin)
        ->post(route('admin.labels.queue.print'), ['start_position' => 1]);

    $response->assertOk()->assertHeader('content-type', 'application/pdf');

    expect(LabelQueueItem::query()->count())->toBe(0);
});

it('creates blank label records carrying nothing but a tag', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('admin.labels.blank'), ['count' => 5, 'start_position' => 1]);

    $response->assertOk()->assertHeader('content-type', 'application/pdf');

    $blanks = Asset::query()->where('status', AssetStatus::Unassigned->value)->get();

    expect($blanks)->toHaveCount(5)
        ->and($blanks->first()->asset_tag)->toBe('INV-GEN-0001')
        ->and($blanks->last()->asset_tag)->toBe('INV-GEN-0005')
        ->and($blanks->first()->name)->toBeNull()
        ->and($blanks->first()->room_id)->toBeNull();
});

it('refuses to generate an unreasonable number of blank labels', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.labels.blank'), ['count' => 5000, 'start_position' => 1])
        ->assertSessionHasErrors('count');
});

it('refuses a start position that is not on the sheet', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.labels.blank'), ['count' => 1, 'start_position' => 25])
        ->assertSessionHasErrors('start_position');
});

it('reprints the identical tag so the item keeps its history', function () {
    $asset = Asset::factory()->create();
    $tagBefore = $asset->asset_tag;

    $this->actingAs($this->admin)
        ->post(route('admin.labels.reprint'), ['asset_ids' => [$asset->id], 'start_position' => 1])
        ->assertOk();

    expect($asset->fresh()->asset_tag)->toBe($tagBefore);
});

it('fits exactly 24 stickers on a page and never spills a row onto the next one', function () {
    // 3 x 70mm is exactly A4 width and 8 x 37mm is 296mm of its 297mm height,
    // so a stray millimetre anywhere pushes a whole column or row onto a new
    // page and ruins the run. 4 skipped cells plus 30 labels is 34 cells,
    // which is two sheets and no more.
    $assets = Asset::factory()->count(30)->create();

    $pdf = app(LabelSheet::class)->render($assets, startPosition: 5)->output();

    expect(preg_match_all('#/Type\s*/Page[^s]#', $pdf))->toBe(2);
});

it('places the last cell of a sheet inside the page', function () {
    $layout = config('school.labels');

    $lastColumnRight = $layout['page_margin_left_mm'] + $layout['columns'] * $layout['width_mm'];
    $lastRowBottom = $layout['page_margin_top_mm'] + $layout['rows'] * $layout['height_mm'];

    expect($lastColumnRight)->toBeLessThanOrEqual(210)
        ->and($lastRowBottom)->toBeLessThanOrEqual(297);
});

it('prints the QR, the tag and the school name on a sticker, and nothing else', function () {
    // No item name: a name can change and the sticker is physical
    // (spec section 6.3).
    $asset = Asset::factory()->create(['name' => 'Epson EB-X41 projector']);

    $html = view('labels.sheet', [
        'pages' => app(LabelSheet::class)->paginate(collect([$asset])),
        'schoolName' => 'Gjimnazi Test',
        'layout' => config('school.labels'),
    ])->render();

    expect($html)
        ->toContain($asset->asset_tag)
        ->toContain('Gjimnazi Test')
        ->toContain('data:image/png;base64')
        ->not->toContain('Epson EB-X41 projector');
});
