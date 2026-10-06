<?php

namespace App\Actions\Auth;

use App\Models\User;

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
        $user = User::create($data);

        return [
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ];
    }
}
