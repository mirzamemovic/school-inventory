<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminAccounts;
use App\Support\AdminPassword;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The person's side of a once-only password link, made when an admin adds
 * their account or resets its password. No login: the link is the whole
 * permission, so it works once, for an hour, and only its hash is stored.
 *
 * Opening the link changes nothing. Messaging apps fetch links to draw a
 * preview, and that must not use it up; only choosing a password does.
 */
class AccountSetupController extends Controller
{
    public function __construct(private readonly AdminAccounts $accounts) {}

    public function show(string $user, string $token): View|Response
    {
        $account = $this->account($user, $token);

        if (! $account) {
            return $this->unavailable();
        }

        return view('account.setup', [
            'user' => $account,
            'action' => route('account.setup.store', ['user' => $account->id, 'token' => $token]),
        ]);
    }

    public function store(Request $request, string $user, string $token): RedirectResponse|Response
    {
        $account = $this->account($user, $token);

        if (! $account) {
            return $this->unavailable();
        }

        $validated = $request->validate([
            'password' => [...AdminPassword::rules($account->email), 'confirmed'],
        ], ['password.confirmed' => __('users.password.mismatch')], ['password' => __('users.password.attribute')]);

        // A second tap a moment ago may have used the link in between.
        if (! $this->accounts->choosePassword($account, $token, $validated['password'])) {
            return $this->unavailable();
        }

        return redirect()
            ->route('login')
            ->withInput(['email' => $account->email])
            ->with('status', __('users.setup.done'));
    }

    /**
     * An unknown account and a dead link look the same from outside, so the
     * address cannot be used to learn which accounts exist.
     */
    private function account(string $id, string $token): ?User
    {
        $account = User::query()->find($id);

        return $account && $this->accounts->linkWorks($account, $token) ? $account : null;
    }

    private function unavailable(): Response
    {
        return response()->view('account.setup-unavailable', status: 410);
    }
}
