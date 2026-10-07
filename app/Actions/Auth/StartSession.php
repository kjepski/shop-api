<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StartSession
{
    public function __construct(private VerifyCredentials $verifyCredentials) {}

    /**
     * Verify credentials and log the user into the cookie session used by the admin panel.
     *
     * @throws ValidationException
     */
    public function handle(string $email, string $password): User
    {
        $user = $this->verifyCredentials->handle($email, $password);

        // login() also regenerates the session id and CSRF token, so values planted before login
        // (session fixation) are worthless.
        Auth::guard('web')->login($user);

        return $user;
    }
}
