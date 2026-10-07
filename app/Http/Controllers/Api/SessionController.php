<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\StartSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Response;

class SessionController extends Controller
{
    /**
     * Cookie login for the admin panel. Other API clients log in with a token via /api/login.
     */
    public function store(LoginRequest $request, StartSession $startSession): UserResource
    {
        // Sanctum starts a session only for requests from a stateful domain (sanctum.stateful).
        abort_unless($request->hasSession(), Response::HTTP_BAD_REQUEST, 'Cookie login is only available to the admin panel; use /api/login for a token.');

        $user = $startSession->handle(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return UserResource::make($user);
    }
}
