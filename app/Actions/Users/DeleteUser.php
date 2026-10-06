<?php

namespace App\Actions\Users;

use App\Exceptions\LastAdminException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteUser
{
    public function __construct(private EnsureAnotherAdminRemains $ensureAnotherAdminRemains) {}

    /**
     * Tokens are polymorphic (no foreign key to cascade), so they are removed explicitly.
     *
     * @throws LastAdminException
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $this->ensureAnotherAdminRemains->handle($user);

            $user->tokens()->delete();
            $user->delete();
        });
    }
}
