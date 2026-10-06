<?php

namespace App\Http\Controllers\Api;

use App\Actions\Users\DeleteUser;
use App\Actions\Users\UpdateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()->orderBy('name')->orderBy('id')->paginate(15);

        return UserResource::collection($users);
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return UserResource::make($user);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): UserResource
    {
        /** @var array{name?: string, email?: string, is_admin?: bool} $data */
        $data = $request->validated();

        return UserResource::make($updateUser->handle($user, $data));
    }

    public function destroy(User $user, DeleteUser $deleteUser): Response
    {
        Gate::authorize('delete', $user);

        $deleteUser->handle($user);

        return response()->noContent();
    }
}
