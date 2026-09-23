<?php

use App\Models\Asset;
use App\Models\BookTitle;
use App\Models\License;
use App\Models\Person;
use App\Models\Report;
use App\Models\Room;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

/* -- the one that matters most ----------------------------------------- */

it('sends a guest to the login screen instead of answering', function () {
    // This route reads serial numbers, every name in the school and every
    // report reference. Section 13 spends real effort keeping that off the
    // public side; an open search route would hand it over in one request.
    $this->get(route('admin.search', ['q' => 'projector']))
        ->assertRedirect(route('login'));
});

it('leaks nothing to a guest even when the term matches something', function () {
    Asset::factory()->create(['name' => 'Epson projector', 'serial_number' => 'SERIAL-9988']);

    $response = $this->get(route('admin.search', ['q' => 'SERIAL-9988']));

    $response->assertRedirect(route('login'))
        ->assertDontSee('SERIAL-9988')
        ->assertDontSee('Epson projector');
});

it('lives under the admin prefix behind the auth middleware', function () {
    $route = collect(Route::getRoutes())->first(fn ($r) => $r->getName() === 'admin.search');

    expect($route->uri())->toBe('admin/search')
        ->and($route->gatherMiddleware())->toContain('auth');
});

it('is reachable from no public page', function () {
    $asset = Asset::factory()->create();

    foreach (['/a/'.$asset->asset_tag, '/a/'.$asset->asset_tag.'/report'] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertDontSee(route('admin.search'), escape: false)
            ->assertDontSee(__('search.label'));
    }
});

/* -- an admin gets results --------------------------------------------- */

it('gives a signed-in admin results', function () {
    Asset::factory()->create(['name' => 'Epson projector']);

    $this->actingAs($this->admin)
        ->get(route('admin.search', ['q' => 'Epson']))
        ->assertOk()
        ->assertSee('Epson projector')
        ->assertSee(__('search.groups.assets'));
});

it('finds an asset by its serial number', function () {
    $wanted = Asset::factory()->create([
        'name' => 'Dell OptiPlex 3070',
        'serial_number' => 'SN-4471-XQ',
    ]);
    Asset::factory()->create(['name' => 'Something else', 'serial_number' => 'SN-0000-AA']);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.search', ['q' => 'SN-4471-XQ']))
        ->assertOk();

    $response->assertSee('Dell OptiPlex 3070')
        ->assertSee($wanted->asset_tag)
        ->assertDontSee('Something else');

    // And it links straight to that asset.
    $response->assertSee(route('admin.assets.show', $wanted), escape: false);
});

it('finds an asset by tag, name, make and model too', function () {
    $asset = Asset::factory()->create([
        'name' => 'Interactive board',
        'make' => 'Promethean',
        'model' => 'ActivPanel-75',
    ]);

    foreach ([$asset->asset_tag, 'Interactive', 'Promethean', 'ActivPanel'] as $term) {
        $this->actingAs($this->admin)
            ->get(route('admin.search', ['q' => $term]))
            ->assertOk()
            ->assertSee('Interactive board');
    }
});

/* -- across the other things ------------------------------------------- */

it('looks in people, rooms, licenses, book titles and report references', function () {
    $person = Person::factory()->inClass('10a')->create(['name' => 'Zamira Krasniqi']);
    $room = Room::factory()->create(['name' => 'Zamira Hall']);
    $license = License::factory()->create(['name' => 'Zamira Antivirus', 'vendor' => 'Zamira BV']);
    $title = BookTitle::factory()->create(['title' => 'Zamira Mathematics']);
    $report = Report::factory()->create(['reference' => 'ZAMIRA']);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.search', ['q' => 'Zamira']))
        ->assertOk();

    $response->assertSee(__('search.groups.people'))->assertSee('Zamira Krasniqi')
        ->assertSee(__('search.groups.rooms'))->assertSee('Zamira Hall')
        ->assertSee(__('search.groups.licenses'))->assertSee('Zamira Antivirus')
        ->assertSee(__('search.groups.books'))->assertSee('Zamira Mathematics')
        ->assertSee(__('search.groups.reports'))->assertSee('ZAMIRA');

    foreach ([
        route('admin.books.student', $person),
        route('admin.rooms.show', $room),
        route('admin.licenses.show', $license),
        route('admin.books.titles.edit', $title),
        route('admin.reports.show', $report),
    ] as $url) {
        $response->assertSee($url, escape: false);
    }
});

it('leaves out the groups that match nothing', function () {
    Asset::factory()->create(['name' => 'Lonely projector']);

    $this->actingAs($this->admin)
        ->get(route('admin.search', ['q' => 'Lonely']))
        ->assertOk()
        ->assertSee(__('search.groups.assets'))
        ->assertDontSee(__('search.groups.rooms'))
        ->assertDontSee(__('search.groups.licenses'));
});

/* -- edges -------------------------------------------------------------- */

it('says plainly when nothing matches', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.search', ['q' => 'nothing-here-at-all']))
        ->assertOk()
        ->assertSee(__('search.nothing', ['term' => 'nothing-here-at-all']))
        ->assertSee(__('search.nothing_help'));
});

it('asks for more than one letter before searching', function () {
    Asset::factory()->create(['name' => 'Epson projector']);

    $this->actingAs($this->admin)
        ->get(route('admin.search', ['q' => 'E']))
        ->assertOk()
        ->assertSee(__('search.too_short'))
        ->assertDontSee('Epson projector');
});

it('invites the admin to type when the box is empty', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.search'))
        ->assertOk()
        ->assertSee(__('search.start_typing'));
});

it('treats a percent sign as a character, not a wildcard', function () {
    Asset::factory()->create(['name' => 'Battery at 50% capacity']);
    Asset::factory()->create(['name' => 'Ordinary projector']);

    $this->actingAs($this->admin)
        ->get(route('admin.search', ['q' => '50%']))
        ->assertOk()
        ->assertSee('Battery at 50% capacity')
        ->assertDontSee('Ordinary projector');
});

/* -- the header box ----------------------------------------------------- */

it('puts the search box on every admin page', function () {
    foreach ([
        route('admin.home'),
        route('admin.assets.index'),
        route('admin.reports.index'),
        route('admin.books.titles'),
        route('admin.manage'),
    ] as $url) {
        $this->actingAs($this->admin)
            ->get($url)
            ->assertOk()
            ->assertSee('data-search-input', escape: false)
            ->assertSee(__('search.label'));
    }
});

it('adds no sixth item to the navigation', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.home'))->assertOk();

    preg_match('#<nav[^>]*>(.*?)</nav>#s', $response->getContent(), $nav);

    expect(substr_count($nav[1], '<li>'))->toBe(5)
        ->and($nav[1])->not->toContain(__('search.label'));
});

it('answers an htmx request with the panel only', function () {
    Asset::factory()->create(['name' => 'Epson projector']);

    $this->actingAs($this->admin)
        ->withHeader('HX-Request', 'true')
        ->get(route('admin.search', ['q' => 'Epson']))
        ->assertOk()
        ->assertSee('Epson projector')
        ->assertDontSee('<!DOCTYPE html>', escape: false);
});

it('marks every result as a link the arrow keys can move to', function () {
    Asset::factory()->create(['name' => 'Epson projector']);

    $this->actingAs($this->admin)
        ->withHeader('HX-Request', 'true')
        ->get(route('admin.search', ['q' => 'Epson']))
        ->assertOk()
        ->assertSee('data-search-result', escape: false);
});
