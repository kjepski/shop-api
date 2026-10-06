<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterUser
{
    /**
     * Create a new user and issue an API token for them.
     *
     * @param  array{name: string, email: string, password: string}  $data
     * @return array{user: User, token: string}
     */
    public function handle(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create($data);

            return [
                'user' => $user,
                'token' => $user->createToken('api')->plainTextToken,
            ];
        });
    }
}
