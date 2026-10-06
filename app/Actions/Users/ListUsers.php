<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListUsers
{
    /**
     * @param  array{search?: string, role?: 'admin'|'user'}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        return User::query()
            ->when(isset($filters['search']), fn (Builder $query) => $query->search($filters['search']))
            ->when(isset($filters['role']), fn (Builder $query) => $query->role($filters['role']))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            // Only applied filters, so unknown query parameters never end up in the links.
            ->appends($filters);
    }
}
