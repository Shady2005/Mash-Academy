<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user && $user->is_active && ! $user->isBlocked() && $user->isStaff());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $actor = $this->user();
        $allowedRoles = $actor && $actor->isSuperAdmin()
            ? [UserRole::Student->value, UserRole::Teacher->value, UserRole::Support->value]
            : [UserRole::Student->value];

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'role' => ['nullable', Rule::in($allowedRoles)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $actor = $this->user();
                if (! $actor || ! $actor->isSuperAdmin()) {
                    return;
                }

                $roleValue = (string) ($this->input('role') ?: UserRole::Student->value);
                if ($roleValue === UserRole::Student->value) {
                    return;
                }

                // Respect the fixed 4-staff hierarchy constraint cleanly
                $exists = User::withTrashed()->where('role', $roleValue)->exists();
                if ($exists) {
                    $validator->errors()->add('role', 'An account with this staff role already exists. Only one account is allowed per fixed staff role.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'You are only permitted to create Student accounts.',
        ];
    }
}
