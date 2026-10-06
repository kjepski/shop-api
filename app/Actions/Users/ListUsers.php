<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListUsers
{
    /**
     * @param  array{search?: string, role?: 'admin'|'user'}  $filters
     * @param  string  $sort  one of User::sortValues()
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(array $filters, string $sort): LengthAwarePaginator
    {
        return User::query()
            ->when(isset($filters['search']), fn (Builder $query) => $query->search($filters['search']))
            ->when(isset($filters['role']), fn (Builder $query) => $query->role($filters['role']))
            ->sorted($sort)
            ->paginate(15);
    }
}
