<?php

use App\Models\Asset;
use App\Models\BookLoan;
use App\Models\Person;
use App\Models\Room;
use App\Models\Supply;
use App\Models\User;
use App\Services\ClassDebtLinks;
use App\Services\LabelSheet;
use App\Services\QrImage;

/*
|--------------------------------------------------------------------------
| The address on a sticker
|--------------------------------------------------------------------------
|
| A sticker cannot be updated, so what goes on it comes from APP_URL and never
| from the address the site happened to be opened from. Print once from a
| temporary address and every label in the building is wrong for ever.
|
| Signed links are the opposite case and stay with the request: they are
| checked against the host they arrive on, and they can simply be made again.
|
*/

beforeEach(function () {
    config(['app.url' => 'https://inventory.school.example']);
});

/** Make the framework believe the site is being used from this address. */
function openSiteFrom(string $address): void
{
    test()->get($address.'/up')->assertOk();

    // Without this the tests below would pass for the wrong reason.
    expect(url('/'))->toBe($address);
}

it('keeps a scan address on APP_URL when the site is opened from a temporary address', function () {
    $asset = Asset::factory()->create();

    openSiteFrom('http://temporary.example');

    expect($asset->scanUrl())->toBe('https://inventory.school.example/a/'.$asset->asset_tag);
});

it('keeps a cupboard address on APP_URL when the site is opened from a temporary address', function () {
    $room = Room::factory()->create();

    openSiteFrom('http://temporary.example');

    expect($room->cupboardUrl())->toBe('https://inventory.school.example/c/'.$room->id);
});

it('draws the QR on a label sheet from APP_URL', function () {
    $asset = Asset::factory()->create();

    openSiteFrom('http://temporary.example');

    $this->mock(QrImage::class)
        ->shouldReceive('pngDataUri')->once()
        ->with('https://inventory.school.example/a/'.$asset->asset_tag)
        ->andReturn('data:image/png;base64,');

    app(LabelSheet::class)->paginate(collect([$asset]));
});

it('draws the QR on the asset page from APP_URL', function () {
    $asset = Asset::factory()->create();

    $this->mock(QrImage::class)
        ->shouldReceive('svg')->once()
        ->with('https://inventory.school.example/a/'.$asset->asset_tag)
        ->andReturn('<svg></svg>');

    $this->actingAs(User::factory()->create())
        ->get('http://temporary.example'.route('admin.assets.show', $asset, false))
        ->assertOk();
});

it('shows the same cupboard address on the room page and the supply page', function () {
    $room = Room::factory()->create();
    $supply = Supply::factory()->create(['room_id' => $room->id]);
    $address = 'https://inventory.school.example/c/'.$room->id;

    $this->actingAs(User::factory()->create());

    $this->get('http://temporary.example/admin/rooms/'.$room->id)
        ->assertOk()
        ->assertSee($address)
        ->assertDontSee('temporary.example/c/');

    $this->get('http://temporary.example/admin/supplies/'.$supply->id.'/edit')
        ->assertOk()
        ->assertSee($address)
        ->assertDontSee('temporary.example/c/');
});

it('copes with a trailing slash on APP_URL', function () {
    config(['app.url' => 'https://inventory.school.example/']);

    $asset = Asset::factory()->create();

    expect($asset->scanUrl())->toBe('https://inventory.school.example/a/'.$asset->asset_tag);
});

it('never prints a plain-HTTP address in production', function () {
    config(['app.url' => 'http://inventory.school.example']);
    app()->detectEnvironment(fn () => 'production');

    $asset = Asset::factory()->create();

    expect($asset->scanUrl())->toBe('https://inventory.school.example/a/'.$asset->asset_tag);
});

it('leaves the rest of the site following the address it was opened from', function () {
    openSiteFrom('http://temporary.example');

    expect(route('login'))->toBe('http://temporary.example/login');
});

it('opens a class link on the address it was made on, final or temporary', function (string $address) {
    $student = Person::factory()->inClass('10a')->create(['name' => 'Arben Krasniqi']);
    BookLoan::factory()->create(['person_id' => $student->id]);

    openSiteFrom($address);

    $url = app(ClassDebtLinks::class)->urlFor('10a');

    expect($url)->toStartWith($address.'/class/10a');

    $this->get($url)->assertOk()->assertSee('Arben Krasniqi');
})->with([
    'the final address' => 'https://inventory.school.example',
    'a temporary address' => 'http://temporary.example',
]);

it('still refuses a class link that is moved to another address', function () {
    Person::factory()->inClass('10a')->create();

    openSiteFrom('https://inventory.school.example');

    $url = app(ClassDebtLinks::class)->urlFor('10a');

    $this->get(str_replace('https://inventory.school.example', 'http://temporary.example', $url))
        ->assertForbidden();
});
