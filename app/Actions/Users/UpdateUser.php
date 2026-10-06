<?php

namespace App\Actions\Users;

use App\Exceptions\LastAdminException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateUser
{
    public function __construct(private EnsureAnotherAdminRemains $ensureAnotherAdminRemains) {}

    /**
     * @param  array{name?: string, email?: string, is_admin?: bool}  $data
     *
     * @throws LastAdminException
     */
    public function handle(User $user, array $data): User
    {
        DB::transaction(function () use ($user, $data): void {
            if (($data['is_admin'] ?? null) === false) {
                $this->ensureAnotherAdminRemains->handle($user);
            }

            $user->fill(array_intersect_key($data, array_flip(['name', 'email'])));

            // Not mass assignable on purpose, so only this admin-only path can change the role.
            if (array_key_exists('is_admin', $data)) {
                $user->is_admin = $data['is_admin'];
            }

            $user->save();
        });

        return $user;
    }
}
