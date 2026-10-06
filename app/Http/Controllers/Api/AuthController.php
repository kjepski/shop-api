<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\LoginUser;
use App\Actions\Auth\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        /** @var array{name: string, email: string, password: string} $data */
        $data = $request->validated();

        ['user' => $user, 'token' => $token] = $registerUser->handle($data);

        return UserResource::make($user)
            ->additional(['token' => $token])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request, LoginUser $loginUser): UserResource
    {
        ['user' => $user, 'token' => $token] = $loginUser->handle(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return UserResource::make($user)->additional(['token' => $token]);
    }

    public function logout(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $user->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
