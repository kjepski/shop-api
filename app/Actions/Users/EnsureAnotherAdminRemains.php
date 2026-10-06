<?php

namespace App\Actions\Users;

use App\Exceptions\LastAdminException;
use App\Models\User;

/**
 * Must run inside the transaction that demotes or deletes $user.
 */
class EnsureAnotherAdminRemains
{
    /**
     * Locks every admin row, so two admins demoting or deleting each other at the same
     * moment run one after another, and the second one sees the result of the first.
     *
     * @throws LastAdminException
     */
    public function handle(User $user): void
    {
        $adminIds = User::query()->where('is_admin', true)->lockForUpdate()->pluck('id');

        // Read under the lock, not from $user, which may be stale.
        $userIsAdmin = $adminIds->contains($user->id);

        if ($userIsAdmin && $adminIds->count() === 1) {
            throw new LastAdminException;
        }
    }
}
