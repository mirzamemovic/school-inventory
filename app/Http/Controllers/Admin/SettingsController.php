<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'schoolName' => Setting::get('school_name', config('app.name')),
            'studentReporting' => Setting::bool('student_reporting_enabled', true),
            'academicYear' => Setting::get('academic_year'),
            'notificationEmail' => Setting::get('notification_email'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'school_name' => ['required', 'string', 'max:255'],
            'student_reporting_enabled' => ['boolean'],
            'academic_year' => ['nullable', 'string', 'regex:#^\d{4}/\d{4}$#'],
            // Present but unused: notifications are in-app only for now.
            'notification_email' => ['nullable', 'email', 'max:255'],
        ]);

        Setting::put('school_name', $validated['school_name']);
        Setting::put('student_reporting_enabled', $request->boolean('student_reporting_enabled') ? '1' : '0');
        Setting::put('academic_year', $validated['academic_year'] ?? '');
        Setting::put('notification_email', $validated['notification_email'] ?? '');

        return redirect()->route('admin.settings.edit')->with('status', __('settings.saved'));
    }
}
