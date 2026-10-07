<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class VerifyCredentials
{
    /**
     * Return the user with the given email and password, shared by token and session login.
     *
     * @throws ValidationException
     */
    public function handle(string $email, string $password): User
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            // Spend the same hashing time as a real check so response timing does not reveal registered emails.
            Hash::make($password);
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return $user;
    }
}
