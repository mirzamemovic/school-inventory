<?php

use App\Models\Asset;
use App\Models\Room;
use App\Models\User;

it('shows an anonymous scanner the item, the room and the status', function () {
    $room = Room::factory()->create(['name' => 'Room 12']);
    $asset = Asset::factory()->create(['name' => 'Epson projector', 'room_id' => $room->id]);

    $this->get('/a/'.$asset->asset_tag)
        ->assertOk()
        ->assertSee('Epson projector')
        ->assertSee('Room 12')
        ->assertSee(__('assets.status.in_use'));
});

it('never leaks anything worth stealing to an anonymous scanner', function () {
    // Tags are sequential, so this page is walkable. It must not turn into a
    // categorised shopping list of the building (spec section 13).
    $asset = Asset::factory()->create([
        'name' => 'Dell laptop',
        'serial_number' => 'SERIAL-9988',
        'purchase_price' => 1299.99,
        'supplier' => 'TechnoMarket Prishtina',
        'invoice_ref' => 'INV-2024-771',
        'warranty_end' => now()->addYear(),
    ]);

    $response = $this->get('/a/'.$asset->asset_tag)->assertOk();

    $response->assertDontSee('SERIAL-9988')
        ->assertDontSee('1299.99')
        ->assertDontSee('TechnoMarket Prishtina')
        ->assertDontSee('INV-2024-771')
        ->assertDontSee(__('assets.labels.warranty_end'));
});

it('keeps the scan page out of search engines', function () {
    $asset = Asset::factory()->create();

    $this->get('/a/'.$asset->asset_tag)
        ->assertOk()
        ->assertSee('noindex, nofollow', escape: false);
});

it('never redirects an anonymous scanner to a login screen', function () {
    $asset = Asset::factory()->create();

    $this->get('/a/'.$asset->asset_tag)->assertOk();
});

it('sends a signed-in admin to the full asset page instead', function () {
    $asset = Asset::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get('/a/'.$asset->asset_tag)
        ->assertRedirect(route('admin.assets.show', $asset));
});

it('explains an unknown code in plain language rather than showing an error page', function () {
    $this->get('/a/INV-PC-9999')
        ->assertNotFound()
        ->assertSee(__('public.not_found'))
        ->assertSee(__('public.not_found_help'));
});

it('tells someone holding an unused label what to do', function () {
    $blank = Asset::factory()->blank()->create();

    $this->get('/a/'.$blank->asset_tag)
        ->assertOk()
        ->assertSee(__('public.blank_heading'));
});
