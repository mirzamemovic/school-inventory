<?php

use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
});

it('keeps settings behind a password', function () {
    $this->get(route('admin.settings.edit'))->assertRedirect(route('login'));
});

it('saves the four settings the spec names', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), [
            'school_name' => 'Gjimnazi Test',
            'student_reporting_enabled' => '1',
            'academic_year' => '2026/2027',
            'notification_email' => 'director@school.example',
        ])
        ->assertRedirect();

    expect(Setting::get('school_name'))->toBe('Gjimnazi Test')
        ->and(Setting::bool('student_reporting_enabled'))->toBeTrue()
        ->and(Setting::get('academic_year'))->toBe('2026/2027')
        ->and(Setting::get('notification_email'))->toBe('director@school.example');
});

it('turns student reporting off when the box is unticked', function () {
    Setting::put('student_reporting_enabled', '1');

    $this->actingAs($this->admin)->put(route('admin.settings.update'), [
        'school_name' => 'Gjimnazi Test',
    ]);

    expect(Setting::bool('student_reporting_enabled', true))->toBeFalse();
});

it('refuses a school year written any other way', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), [
            'school_name' => 'Gjimnazi Test',
            'academic_year' => 'last year',
        ])
        ->assertSessionHasErrors('academic_year');
});

it('puts the school name on every page', function () {
    Setting::put('school_name', 'Gjimnazi Test');

    $this->actingAs($this->admin)
        ->get(route('admin.home'))
        ->assertOk()
        ->assertSee('Gjimnazi Test');
});

// Changed 2026-10-01: accounts are now managed in
// the browser (Manage → Users), behind a fresh password re-entry, instead of
// only from the command line. UserManagementTest covers the rest.
it('lists the accounts only after the password is typed again', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertRedirect(route('admin.password.confirm'));

    $this->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee($this->admin->email);
});

it('serves a robots file that keeps the whole application out of search', function () {
    expect(file_get_contents(public_path('robots.txt')))->toContain('Disallow: /');
});
