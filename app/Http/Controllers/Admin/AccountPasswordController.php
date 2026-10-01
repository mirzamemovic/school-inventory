<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAccounts;
use App\Support\AdminPassword;
use App\Support\PasswordRecheck;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Change my password", for whoever is signed in. The current password is
 * asked for on the form itself, so this needs no separate re-entry step.
 * Every other device is signed out; this one carries on.
 */
class AccountPasswordController extends Controller
{
    public function edit(): View
    {
        return view('admin.account.password');
    }

    public function update(Request $request, AdminAccounts $accounts): RedirectResponse
    {
        PasswordRecheck::verify($request, 'current_password');

        $user = $request->user();

        $validated = $request->validate([
            'password' => [...AdminPassword::rules($user->email), 'confirmed', 'different:current_password'],
        ], [
            'password.confirmed' => __('users.password.mismatch'),
            'password.different' => __('users.password.unchanged'),
        ], ['password' => __('users.password.attribute')]);

        $accounts->changeOwnPassword($user, $validated['password']);

        // Its old session was signed out with the others; carry on under a new one.
        $request->session()->regenerate(destroy: true);

        return redirect()
            ->route('admin.manage')
            ->with('status', __('users.password.changed'));
    }
}
