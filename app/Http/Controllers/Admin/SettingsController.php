<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Support\EventAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function update(Request $request, AuditLogger $log): RedirectResponse
    {
        $validated = $request->validate([
            'school_name' => ['required', 'string', 'max:255'],
            'student_reporting_enabled' => ['boolean'],
            'academic_year' => ['nullable', 'string', 'regex:#^\d{4}/\d{4}$#'],
            // Present but unused: notifications are in-app only for now.
            'notification_email' => ['nullable', 'email', 'max:255'],
        ]);

        $after = [
            'school_name' => $validated['school_name'],
            'student_reporting_enabled' => $request->boolean('student_reporting_enabled') ? '1' : '0',
            'academic_year' => $validated['academic_year'] ?? '',
            'notification_email' => $validated['notification_email'] ?? '',
        ];

        DB::transaction(function () use ($request, $after, $log) {
            // Read the way the form reads them, defaults included, so a first
            // save does not log changes nobody made.
            $before = [
                'school_name' => (string) Setting::get('school_name', config('app.name')),
                'student_reporting_enabled' => Setting::bool('student_reporting_enabled', true) ? '1' : '0',
                'academic_year' => (string) Setting::get('academic_year', ''),
                'notification_email' => (string) Setting::get('notification_email', ''),
            ];

            foreach ($after as $key => $value) {
                Setting::put($key, $value);
            }

            // Turning student reporting off, or changing the school year that
            // every due date hangs on, is worth being able to trace. Logged
            // against the admin, as there is no one record it belongs to.
            $log->recordChanges($request->user(), EventAction::SETTINGS_CHANGED, $before, $after);
        });

        return redirect()->route('admin.settings.edit')->with('status', __('settings.saved'));
    }
}
