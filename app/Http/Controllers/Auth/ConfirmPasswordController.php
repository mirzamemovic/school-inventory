<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\PasswordRecheck;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Type your password again" before Manage → Users. The server records when
 * it was last typed and every user-management route checks it; hiding a
 * button would protect nothing.
 */
class ConfirmPasswordController extends Controller
{
    /** Ten minutes: long enough to add someone and send their link. */
    public const MINUTES = 10;

    public function show(): View
    {
        return view('auth.confirm-password');
    }

    public function store(Request $request): RedirectResponse
    {
        PasswordRecheck::verify($request, 'password');

        $request->session()->passwordConfirmed();

        return redirect()->intended(route('admin.users.index'));
    }
}
