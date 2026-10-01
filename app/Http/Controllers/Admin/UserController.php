<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\AdminAccounts;
use App\Services\QrImage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Manage → Users: the accounts that can sign in. Every one is an admin.
 *
 * Every route here sits behind a fresh password re-entry (routes/web.php), so
 * an unlocked computer left in the office is not a way to make an account.
 * Nothing here can show a password: none is ever stored in a form that could
 * be read back. Resetting one produces a once-only link with which the person
 * chooses a new one themselves.
 */
class UserController extends Controller
{
    public function __construct(private readonly AdminAccounts $accounts) {}

    public function index(Request $request): View
    {
        return view('admin.users.index', [
            // Active first, then switched off; alphabetical within each.
            'users' => User::query()->orderByRaw('deactivated_at is not null')->orderBy('name')->get(),
            'me' => $request->user(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.create');
    }

    public function store(UserRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        [$user, $link] = $this->accounts->create($request->user(), $request->details());

        return $this->showLink($user, $link, __('users.created', ['name' => $user->name]));
    }

    public function show(Request $request, User $user, QrImage $qr): View
    {
        // Shown once, straight after it was made, and only on the page of the
        // account it belongs to. Only its hash is kept, so it cannot be shown
        // again: a lost link is replaced, never recovered.
        $link = $request->session()->get('setup_link');
        $link = ($link['user'] ?? null) === $user->id ? $link : null;

        return view('admin.users.show', [
            'user' => $user,
            'me' => $request->user(),
            'link' => $link,
            'qrSvg' => $link ? $qr->svg($link['url']) : null,
        ]);
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.edit', ['user' => $user]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $this->accounts->update($request->user(), $user, $request->details());

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', __('users.updated'));
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);

        // The same step replaces a lost link for someone who never chose a
        // password; "reset" would be the wrong word for them.
        $message = $user->isWaitingForPassword() ? 'users.new_link_done' : 'users.reset_done';

        $link = $this->accounts->resetPassword($request->user(), $user);

        return $this->showLink($user, $link, __($message, ['name' => $user->name]));
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('deactivate', $user);

        $this->accounts->deactivate($request->user(), $user);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', __('users.deactivated', ['name' => $user->name]));
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('reactivate', $user);

        $this->accounts->reactivate($request->user(), $user);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', __('users.reactivated', ['name' => $user->name]));
    }

    private function showLink(User $user, string $link, string $status): RedirectResponse
    {
        return redirect()
            ->route('admin.users.show', $user)
            ->with('status', $status)
            ->with('setup_link', [
                'user' => $user->id,
                'url' => $link,
                'expires_at' => $this->accounts->linkExpiresAt()->format('H:i'),
            ]);
    }
}
