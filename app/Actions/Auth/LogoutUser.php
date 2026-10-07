<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutUser
{
    /**
     * Revoke the token the request was made with, or end the cookie session of the admin panel
     * (together with a Bearer token sent alongside it).
     */
    public function handle(Request $request, User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();

            return;
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // The session wins over a Bearer token sent from the same browser (old /admin panel next to
        // /admin-next). Revoke that token too, so logging out never leaves it valid.
        $bearerToken = $request->bearerToken();
        if ($bearerToken !== null) {
            PersonalAccessToken::findToken($bearerToken)?->delete();
        }
    }
}
