<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
| Cross-cutting protections that do not belong to any one screen. Each test
| names the attack it stands in front of.
*/

it('shows asset photos to anyone, because the scan page does', function () {
    Storage::fake('local');
    $path = 'assets/'.Str::random(40).'.jpg';
    Storage::disk('local')->put($path, 'jpeg bytes');

    $this->get(route('photo', ['path' => $path]))->assertOk();
});

it('keeps report photos behind the admin login', function () {
    // Only the director looks at these, and a photo taken in a classroom can
    // show a student. A stranger gets the same answer as for a missing file.
    Storage::fake('local');
    $path = 'reports/'.Str::random(40).'.jpg';
    Storage::disk('local')->put($path, 'jpeg bytes');

    $this->get(route('photo', ['path' => $path]))->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get(route('photo', ['path' => $path]))
        ->assertOk();
});
