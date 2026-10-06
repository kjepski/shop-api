<?php

namespace App\Http\Requests\Users;

use App\Http\Requests\Concerns\DetectsFilterInput;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IndexUserRequest extends FormRequest
{
    use DetectsFilterInput;

    /**
     * Query parameters that narrow the list; together with sort, must match the keys of rules().
     */
    public const FILTERS = ['search', 'role'];

    public function authorize(): Response
    {
        return Gate::inspect('viewAny', User::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'min:2', 'max:100'],
            'role' => ['nullable', 'string', Rule::in(['admin', 'user'])],
            'sort' => ['nullable', 'string', Rule::in(User::sortValues())],
        ];
    }

    /**
     * The requested sort, or the default one; never a filter, so it does not count as a search.
     */
    public function sort(): string
    {
        return $this->filled('sort') ? $this->string('sort')->toString() : User::DEFAULT_SORT;
    }

    /**
     * Query parameters for pagination links: applied filters and a non-default sort only,
     * so unknown parameters never end up in (cached) links.
     *
     * @return array<string, string|int|true>
     */
    public function linkParameters(): array
    {
        $sort = $this->sort();

        return $sort === User::DEFAULT_SORT ? $this->filters() : [...$this->filters(), 'sort' => $sort];
    }

    /**
     * Only the filters that are actually applied, so they can drive the query and pagination links.
     *
     * @return array{search?: string, role?: 'admin'|'user'}
     */
    public function filters(): array
    {
        $filters = [];

        if ($this->filled('search')) {
            $filters['search'] = $this->string('search')->toString();
        }

        if ($this->filled('role')) {
            $filters['role'] = $this->string('role')->toString() === 'admin' ? 'admin' : 'user';
        }

        return $filters;
    }
}
