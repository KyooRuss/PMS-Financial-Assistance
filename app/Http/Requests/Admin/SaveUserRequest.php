<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Creating or editing an account. Water district users need a water district; office staff must not have one.
 */
class SaveUserRequest extends FormRequest
{
    /**
     * Usernames are stored in lowercase, so "JDelaCruz" becomes "jdelacruz".
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => strtolower(trim($this->input('username')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'username' => User::usernameRules($user),
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user)],
            'role' => ['required', new Enum(Role::class)],
            'water_district_id' => [
                Rule::requiredIf($this->input('role') === Role::Lwd->value),
                Rule::prohibitedIf($this->input('role') !== Role::Lwd->value),
                'nullable', 'exists:water_districts,id',
            ],
            // New accounts get a temporary password; existing ones use "Reset password" instead.
            'password' => $user ? ['prohibited'] : ['required', 'string', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => __('Use 2–50 lowercase letters, numbers, dots, dashes or underscores.'),
            'water_district_id.required' => __('Choose the water district this user belongs to.'),
            'water_district_id.prohibited' => __('Only water district users belong to a water district.'),
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $user = $this->route('user');

            // The System Admin cannot take away their own admin access.
            if ($user && $user->is($this->user()) && $this->input('role') !== Role::SystemAdmin->value) {
                $validator->errors()->add('role', __('You cannot change your own role.'));
            }
        }];
    }
}
