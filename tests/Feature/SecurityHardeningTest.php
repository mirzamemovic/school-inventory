<?php

use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Facades\File;
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

it('sends security headers on public and admin pages alike', function () {
    $asset = Asset::factory()->create();

    foreach ([
        $this->get('/a/'.$asset->asset_tag),
        $this->actingAs(User::factory()->create())->get(route('admin.home')),
    ] as $response) {
        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'same-origin');

        $policy = $response->headers->get('Content-Security-Policy');

        // Scripts from this site's own files only: a description that ever
        // slipped past escaping still could not run as the director.
        expect($policy)->toContain("script-src 'self'")
            ->and($policy)->toContain("frame-ancestors 'none'")
            ->and(Str::betweenFirst($policy, 'script-src', ';'))->not->toContain('unsafe');
    }
});

it('has no inline event handlers or scripts in any view, which the policy would block', function () {
    $offenders = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
        ->reject(fn ($file) => str_contains($file->getPathname(), 'labels'.DIRECTORY_SEPARATOR.'sheet'))
        ->filter(fn ($file) => preg_match('/\son[a-z]+\s*=\s*["\']|<script(?![^>]*\bsrc=)/i', $file->getContents()))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values();

    expect($offenders->all())->toBe([]);
});

it('turns plain HTTP away in production, and says to stay on HTTPS', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('http://school.example/a/INV-PC-0001')
        ->assertStatus(308)
        ->assertRedirect('https://school.example/a/INV-PC-0001');

    $this->get('https://school.example/privacy')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security');

    // A host that ends HTTPS at a proxy in front of PHP must not loop.
    $this->withHeader('X-Forwarded-Proto', 'https')->get('http://school.example/privacy')->assertOk();
});

it('fails closed if the document root is ever set to the project folder', function () {
    // Insurance for the worst mistake in DEPLOYMENT.md: .env downloadable.
    $rules = file_get_contents(base_path('.htaccess'));

    expect($rules)->toContain('Require all denied')
        ->and($rules)->toContain('Deny from all');

    // Apache applies parent folders' .htaccess too, so without an explicit
    // grant here the denial above would take the whole site down.
    $public = file_get_contents(public_path('.htaccess'));

    expect($public)->toContain('Require all granted')
        ->and($public)->toContain('Allow from all');
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
