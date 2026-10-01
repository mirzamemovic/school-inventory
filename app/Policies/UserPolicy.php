<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * What one admin may do to an account. Every account is an admin, so these are
 * not permissions: they are the guardrails that stop the school locking itself
 * out with no way back in.
 *
 * There is no separate "never deactivate the last admin" rule because it
 * follows from two that are here: nobody can deactivate or reset their own
 * account, and whoever acts must still be able to sign in. Whoever acts is
 * therefore always left. AdminAccounts checks again under a lock, so two
 * admins switching each other off at the same moment cannot both succeed.
 */
class UserPolicy
{
    /** Someone switched off or reset a moment ago cannot act at all. */
    public function before(User $me): ?Response
    {
        return $me->canSignIn() ? null : Response::deny(__('users.rules.you_cannot_sign_in'));
    }

    public function create(User $me): Response
    {
        return $this->roomForAnother();
    }

    public function update(User $me, User $user): bool
    {
        return true;
    }

    public function resetPassword(User $me, User $user): Response
    {
        if ($me->is($user)) {
            return Response::deny(__('users.rules.reset_yourself'));
        }

        if ($user->isDeactivated()) {
            return Response::deny(__('users.rules.reset_deactivated'));
        }

        return Response::allow();
    }

    public function deactivate(User $me, User $user): Response
    {
        if ($me->is($user)) {
            return Response::deny(__('users.rules.deactivate_yourself'));
        }

        if ($user->isDeactivated()) {
            return Response::deny(__('users.rules.already_deactivated'));
        }

        return Response::allow();
    }

    public function reactivate(User $me, User $user): Response
    {
        if (! $user->isDeactivated()) {
            return Response::deny(__('users.rules.already_active'));
        }

        return $this->roomForAnother();
    }

    private function roomForAnother(): Response
    {
        return User::query()->active()->count() < User::MAX_ACTIVE
            ? Response::allow()
            : Response::deny(__('users.rules.full', ['max' => User::MAX_ACTIVE]));
    }
}
