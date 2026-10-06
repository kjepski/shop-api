<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Inactive products are hidden from regular users as if they did not exist.
     */
    public function view(User $user, Product $product): Response
    {
        return $product->is_active || $user->is_admin
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Product $product): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->is_admin;
    }
}
