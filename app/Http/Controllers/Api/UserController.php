<?php

namespace App\Http\Controllers\Api;

use App\Actions\Users\DeleteUser;
use App\Actions\Users\ListUsers;
use App\Actions\Users\UpdateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\IndexUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(IndexUserRequest $request, ListUsers $listUsers): AnonymousResourceCollection
    {
        $users = $listUsers->handle($request->filters(), $request->sort())->appends($request->linkParameters());

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
