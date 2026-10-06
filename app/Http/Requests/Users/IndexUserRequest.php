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
     * Query parameters that narrow the list; must match the keys of rules().
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
        ];
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
