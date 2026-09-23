<?php

use App\Enums\AssetStatus;
use App\Exceptions\TagIsWriteOnce;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Room;
use App\Services\AssetTagGenerator;

it('builds a tag from the school prefix, the category prefix and a four digit sequence', function () {
    $category = Category::factory()->create(['prefix' => 'PRJ']);

    expect(app(AssetTagGenerator::class)->format($category, 31))->toBe('INV-PRJ-0031');
});

it('uses the blank prefix when there is no category yet', function () {
    expect(app(AssetTagGenerator::class)->format(null, 7))->toBe('INV-GEN-0007');
});

it('keeps the sequence global so two categories can never collide', function () {
    $generator = app(AssetTagGenerator::class);
    $pcs = Category::factory()->create(['prefix' => 'PC']);
    $projectors = Category::factory()->create(['prefix' => 'PRJ']);
    $room = Room::factory()->create();

    $first = $generator->assignAndSave(new Asset(['category_id' => $pcs->id, 'room_id' => $room->id, 'name' => 'PC']));
    $second = $generator->assignAndSave(new Asset(['category_id' => $projectors->id, 'room_id' => $room->id, 'name' => 'Projector']));

    expect($first->tag_sequence)->toBe(1)
        ->and($second->tag_sequence)->toBe(2)
        ->and($first->asset_tag)->toBe('INV-PC-0001')
        ->and($second->asset_tag)->toBe('INV-PRJ-0002');
});

it('refuses to change a tag once it is assigned', function () {
    $asset = Asset::factory()->create();

    $asset->asset_tag = 'INV-PC-9999';

    expect(fn () => $asset->save())->toThrow(TagIsWriteOnce::class);
});

it('refuses to change the sequence behind a tag', function () {
    $asset = Asset::factory()->create();

    $asset->tag_sequence = 4321;

    expect(fn () => $asset->save())->toThrow(TagIsWriteOnce::class);
});

it('keeps the tag when the category changes, because the sticker cannot be reprinted', function () {
    $projectors = Category::factory()->create(['prefix' => 'PRJ']);
    $audio = Category::factory()->create(['prefix' => 'AUD']);

    $asset = app(AssetTagGenerator::class)->assignAndSave(
        new Asset(['category_id' => $projectors->id, 'room_id' => Room::factory()->create()->id, 'name' => 'Projector'])
    );
    $tag = $asset->asset_tag;

    $asset->update(['category_id' => $audio->id]);

    expect($asset->fresh()->asset_tag)->toBe($tag);
});

it('cannot have its tag set by mass assignment', function () {
    $asset = Asset::factory()->create();

    $asset->fill(['asset_tag' => 'INV-PC-0001', 'name' => 'Renamed']);

    expect($asset->asset_tag)->not->toBe('INV-PC-0001')
        ->and($asset->name)->toBe('Renamed');
});

it('treats a pre-printed label as blank until it is filled in', function () {
    $blank = Asset::factory()->blank()->create();

    expect($blank->isBlank())->toBeTrue()
        ->and($blank->status)->toBe(AssetStatus::Unassigned)
        ->and($blank->room_id)->toBeNull();
});
