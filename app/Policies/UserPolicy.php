<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, User $model): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, User $model): bool
    {
        return $user->is_admin;
    }

    /**
     * Admins cannot delete themselves; the DeleteUser action also keeps at least one admin.
     */
    public function delete(User $user, User $model): Response
    {
        if (! $user->is_admin) {
            return Response::deny();
        }

        return $user->is($model)
            ? Response::deny('You cannot delete your own account.')
            : Response::allow();
    }
}
