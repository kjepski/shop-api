<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->targetUser());
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $target = $this->targetUser();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target)],
            'is_admin' => [
                'sometimes',
                'required',
                'boolean:strict',
                // Admins cannot demote themselves; the UpdateUser action also keeps at least one admin.
                function (string $attribute, mixed $value, Closure $fail) use ($target): void {
                    if ($value === false && $target->is($this->user())) {
                        $fail('You cannot remove your own admin role.');
                    }
                },
            ],
        ];
    }

    private function targetUser(): User
    {
        /** @var User */
        return $this->route('user');
    }
}
